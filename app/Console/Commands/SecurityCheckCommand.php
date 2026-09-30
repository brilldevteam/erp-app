<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * Read-only production readiness check: debug mode, default passwords, demo accounts and exposed files.
 */
class SecurityCheckCommand extends Command
{
    protected $signature = 'security:check';

    protected $description = 'Report insecure production settings, default passwords, demo accounts and exposed files (changes nothing)';

    // Passwords shipped with the product or commonly left as defaults.
    private const DEFAULT_PASSWORDS = ['1234', '12345', '123456', '12345678', 'password', 'admin', 'secret'];

    private int $failures = 0;

    public function handle(): int
    {
        $this->line('Environment');
        $production = app()->environment('production');
        $this->report($production, 'APP_ENV is production', 'APP_ENV is "' . app()->environment() . '" (set APP_ENV=production on the live server)');
        $this->report(!config('app.debug'), 'APP_DEBUG is off', 'APP_DEBUG is on: error pages expose code and configuration (set APP_DEBUG=false)');
        $this->report(File::exists(storage_path('installed')), 'Installer is locked (storage/installed exists)', 'storage/installed is missing: the /install wizard is reachable and can reinstall the database');

        $this->newLine();
        $this->line('Accounts');
        // Checking every password hash is slow, so only admin/company accounts and demo addresses are tested.
        $accounts = User::query()
            ->where(fn ($query) => $query->whereIn('type', ['superadmin', 'company'])->orWhere('email', 'like', '%@example.com'))
            ->get(['id', 'email', 'type', 'password']);
        $weak = $accounts->filter(fn (User $user) => collect(self::DEFAULT_PASSWORDS)->contains(fn ($password) => Hash::check($password, $user->password)));
        $this->report($weak->isEmpty(), "No default passwords among {$accounts->count()} admin, company and demo accounts",
            'Accounts using a default password: ' . $weak->map(fn ($user) => "{$user->email} ({$user->type})")->implode(', '));
        $demo = $accounts->filter(fn (User $user) => str_ends_with(strtolower($user->email), '@example.com'));
        $this->report($demo->isEmpty(), 'No demo @example.com accounts',
            'Demo accounts still present (change their email to a real address or delete them): ' . $demo->pluck('email')->implode(', '));

        $this->newLine();
        $this->line('Files');
        $exposed = collect(['phpinfo.php', 'server-requirements.php'])->filter(fn ($file) => File::exists(base_path($file)));
        $this->report($exposed->isEmpty(), 'No diagnostic scripts in the project root', 'Delete from the server: ' . $exposed->implode(', '));
        $dumps = collect(['*.sql', '*.sql.gz', 'backups/*.sql', 'backups/*.sql.gz', 'public/*.sql', 'public/*.sql.gz'])
            ->flatMap(fn ($pattern) => File::glob(base_path($pattern)))
            ->map(fn ($path) => ltrim(str_replace([base_path(), '\\'], ['', '/'], $path), '/'));
        $this->report($dumps->isEmpty(), 'No database dumps inside the application folder', 'Move database dumps off the web server: ' . $dumps->implode(', '));

        $this->newLine();
        if ($this->failures) {
            $this->error("{$this->failures} issue(s) found.");
            return self::FAILURE;
        }
        $this->info('All checks passed.');
        return self::SUCCESS;
    }

    private function report(bool $passed, string $ok, string $problem): void
    {
        if ($passed) {
            $this->line("  <info>PASS</info>  {$ok}");
            return;
        }
        $this->failures++;
        $this->line("  <error>FAIL</error>  {$problem}");
    }
}
