<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records loaded from the URL by route model binding must belong to the signed-in user's company.
 *
 * Tenant data is separated by created_by (the owning company). Records created by a staff member of the same
 * company, and global records owned by the super admin (plans, templates, ...), are allowed. Anything else is
 * reported as not found, so another company's record IDs cannot be read or changed through a URL.
 */
class EnsureRouteModelsBelongToCompany
{
    private array $companyOf = [];

    public function handle(Request $request, Closure $next): Response
    {
        // API requests are authenticated by token after this middleware runs, so resolve that user directly.
        $user = $request->user() ?? ($request->bearerToken() ? $request->user('sanctum') : null);
        if (!$user || $user->type === 'superadmin' || !$request->route() || !$this->requiresLogin($request)) {
            return $next($request);
        }

        $companyId = $user->type === 'company' ? (int) $user->id : (int) $user->created_by;
        foreach ($request->route()->parameters() as $parameter) {
            if (!$parameter instanceof Model || !array_key_exists('created_by', $parameter->getAttributes())) {
                continue;
            }

            $ownerId = $parameter->getAttribute('created_by');
            if ($ownerId === null || (int) $ownerId === $companyId) {
                continue;
            }

            $ownerCompany = $this->companyOf((int) $ownerId);
            if ($ownerCompany !== 'superadmin' && $ownerCompany !== $companyId) {
                abort(404);
            }
        }

        return $next($request);
    }

    /** Public pages (booking pages, shared links) may show another company's records to a signed-in visitor. */
    private function requiresLogin(Request $request): bool
    {
        foreach ($request->route()->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && ($middleware === 'auth' || str_starts_with($middleware, 'auth:'))) {
                return true;
            }
        }

        return false;
    }

    /** The company a user belongs to, or 'superadmin' for records owned by the platform. */
    private function companyOf(int $userId): int|string|null
    {
        if (!array_key_exists($userId, $this->companyOf)) {
            $owner = User::query()->whereKey($userId)->first(['id', 'type', 'created_by']);
            $this->companyOf[$userId] = match (true) {
                $owner === null => null,
                $owner->type === 'superadmin' => 'superadmin',
                $owner->type === 'company' => (int) $owner->id,
                default => (int) $owner->created_by,
            };
        }

        return $this->companyOf[$userId];
    }
}
