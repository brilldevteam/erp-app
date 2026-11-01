<?php

namespace Tests\Smoke;

use App\Models\User;
use App\Services\AuthSessionService;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

/**
 * Opens every page (GET route) as one company and reports any that answer with a server error (500).
 *
 * Local only: run with `php vendor/bin/phpunit -c phpunit.smoke.xml` against the local copy of live data. Everything
 * runs inside one database transaction that is rolled back, mail goes to the array driver, and the test refuses to run
 * unless APP_URL points at this machine. Routes that act instead of show (log out, delete, approve, send, payment
 * gateways, cache clearing, ...) are not opened. The report is written to storage/logs/page-check-<time>.txt.
 *
 * SMOKE_USER_ID picks the account (default 664, Brill Creations).
 */
class PageSmokeTest extends TestCase
{
    use DatabaseTransactions;

    private const SKIP = '/(^|[.\/_-])(logout|login|register|password|verification|verify|impersonat\w*|leave\w*|install\w*|cache|clear\w*|migrate|optimize|backup|update-system|upgrade|delete|destroy|remove|approve|reject|cancel|send|sync|toggle|mark-?\w*|read-?all|webhook|callback|checkout|stripe|paypal|pay-?now|social|oauth|provider|ai-\w*|chat|pusher|broadcasting|sanctum|storage|up)([.\/_-]|$)/i';

    private const NO_PAGE = '/(Method \S+::(create|show|edit) does not exist|Call to undefined method \S+Controller::(create|show|edit)\(\))/';

    private const USER_TYPES = ['customer' => 'client', 'customerId' => 'client', 'vendor' => 'vendor', 'vendorId' => 'vendor', 'user' => null];

    private const VALUES = ['stageKey' => 'customers', 'format' => 'xlsx', 'lang' => 'en', 'locale' => 'en', 'template' => 'customers'];

    public function test_pages_do_not_answer_with_a_server_error(): void
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (!in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $this->markTestSkipped("Page check is local only; APP_URL host is {$host}.");
        }

        $this->withoutVite();
        $user = User::findOrFail((int) (getenv('SMOKE_USER_ID') ?: 664));
        $tenantId = in_array($user->type, ['company', 'superadmin'], true) ? $user->id : (int) $user->created_by;
        $slug = (string) User::whereKey($tenantId)->value('slug');
        // The session marker a real log-in sets; without it the session counts as revoked and every page redirects to /login.
        $session = [AuthSessionService::SESSION_KEY => max(1, (int) ($user->security_version ?? 1))];

        $results = ['ok' => [], 'error' => [], 'login' => [], 'no_page' => [], 'skipped' => []];
        foreach (Route::getRoutes() as $route) {
            if (!in_array('GET', $route->methods(), true) || !in_array('web', $route->gatherMiddleware(), true)) {
                continue;
            }
            $label = ($route->getName() ?: '-').'  /'.ltrim($route->uri(), '/');
            if (preg_match(self::SKIP, (string) $route->getName()) || preg_match(self::SKIP, $route->uri())) {
                $results['skipped'][] = "{$label}  (acts, not a page)";
                continue;
            }

            $uri = $this->uri($route, $tenantId, $slug, $missing);
            if ($uri === null) {
                $results['skipped'][] = "{$label}  (no value for {{$missing}})";
                continue;
            }

            try {
                $response = $this->actingAs($user)->withSession($session)->get($uri);
                $status = $response->getStatusCode();
                $message = $status >= 500 ? ($response->exception?->getMessage() ?: 'Server error') : null;
            } catch (Throwable $exception) {
                [$status, $message] = [500, $exception->getMessage()];
            }

            $location = isset($response) ? (string) $response->headers->get('Location') : '';
            if ($status >= 500 && preg_match(self::NO_PAGE, (string) $message)) {
                // A resource address (create/show/edit) whose screen is a pop-up on the list: no menu links here.
                $results['no_page'][] = "/{$uri}  [{$route->getName()}]";
            } elseif ($status >= 500) {
                $results['error'][] = "{$status}  /{$uri}  [{$route->getName()}]\n      ".mb_substr((string) $message, 0, 400);
            } elseif (str_ends_with($location, '/login')) {
                // Sent to the log-in page: the page itself never ran, so it was not checked.
                $results['login'][] = "{$status}  /{$uri}";
            } else {
                $results['ok'][] = "{$status}  /{$uri}".($location ? "  -> {$location}" : '');
            }
            unset($response);
        }

        $report = storage_path('logs/page-check-'.now()->format('Ymd-His').'.txt');
        file_put_contents($report, implode("\n", [
            'Page check as user '.$user->id.' ('.$user->name.') on '.now()->toDateTimeString(),
            'Opened: '.(count($results['ok']) + count($results['error']) + count($results['no_page'])).'  Server errors: '.count($results['error']).'  Sent to log-in (not checked): '.count($results['login']).'  Skipped: '.count($results['skipped']),
            '', '== SERVER ERRORS ==', ...($results['error'] ?: ['none']),
            '', '== SENT TO LOG-IN (NOT CHECKED) ==', ...($results['login'] ?: ['none']),
            '', '== ADDRESS WITH NO PAGE (create/show/edit done in a pop-up; not linked from menus) ==', ...($results['no_page'] ?: ['none']),
            '', '== SKIPPED ==', ...$results['skipped'],
            '', '== OPENED WITHOUT SERVER ERROR ==', ...$results['ok'],
        ]));
        fwrite(STDOUT, "\nPage check report: {$report}\nOpened ".(count($results['ok']) + count($results['error'])).', server errors '.count($results['error']).', sent to log-in '.count($results['login']).', skipped '.count($results['skipped'])."\n");

        $this->assertSame([], $results['login'], 'Pages were sent to the log-in page, so they were not checked. See '.$report);
        $this->assertSame([], $results['error'], "Pages with server errors:\n".implode("\n", $results['error']));
    }

    /** The URL with every route parameter filled from a record of the tenant, or null when one cannot be filled. */
    private function uri(LaravelRoute $route, int $tenantId, string $slug, ?string &$missing): ?string
    {
        $models = [];
        foreach ($route->signatureParameters(['subClass' => UrlRoutable::class]) as $parameter) {
            $type = $parameter->getType();
            if ($type && !$type->isBuiltin()) {
                // Laravel binds {sales_invoice} to $salesInvoice, so match both spellings.
                $models[Str::snake($parameter->getName())] = $type->getName();
            }
        }

        $values = [];
        foreach ($route->parameterNames() as $name) {
            $optional = str_contains($route->uri(), '{'.$name.'?}');
            $model = $models[Str::snake($name)] ?? null;
            $value = match (true) {
                $name === 'userSlug' || $name === 'slug' => $slug ?: null,
                isset(self::VALUES[$name]) => self::VALUES[$name],
                $model !== null && is_subclass_of($model, Model::class) => $this->recordKey($model, $tenantId),
                // Controllers that take a plain id for a customer, vendor or user.
                isset(self::USER_TYPES[$name]) => User::where('created_by', $tenantId)->when(self::USER_TYPES[$name], fn ($query, $type) => $query->where('type', $type))->value('id'),
                default => null,
            };
            if ($value === null && !$optional) {
                $missing = $name;

                return null;
            }
            if ($value !== null) {
                $values[$name] = $value;
            }
        }

        if ($route->getName()) {
            return ltrim(route($route->getName(), $values, false), '/');
        }
        $uri = $route->uri();
        foreach ($route->parameterNames() as $name) {
            $uri = str_replace(['{'.$name.'}', '{'.$name.'?}'], (string) ($values[$name] ?? ''), $uri);
        }

        return trim(preg_replace('#/+#', '/', $uri), '/');
    }

    private function recordKey(string $class, int $tenantId): mixed
    {
        try {
            $model = new $class();
            $query = $class::query();
            if (Schema::hasColumn($model->getTable(), 'created_by')) {
                $query->where($model->qualifyColumn('created_by'), $tenantId);
            }

            return $query->value($model->getRouteKeyName());
        } catch (Throwable) {
            return null;
        }
    }
}
