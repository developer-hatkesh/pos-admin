<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Filament\Resources\Companies\CompanyResource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictPlatformSuperAdminAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'hasSuperAdminRole') || ! $user->hasSuperAdminRole()) {
            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();

        if ($routeName === 'filament.admin.pages.dashboard') {
            return redirect(CompanyResource::getUrl());
        }

        if ($this->isAllowedRoute($routeName)) {
            return $next($request);
        }

        abort(403);
    }

    private function isAllowedRoute(string $routeName): bool
    {
        foreach ($this->allowedRoutePrefixes() as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return in_array($routeName, [
            'filament.admin.auth.logout',
            'filament.admin.auth.profile',
        ], true);
    }

    /**
     * @return list<string>
     */
    private function allowedRoutePrefixes(): array
    {
        return [
            'filament.admin.resources.account-categories.',
            'filament.admin.resources.account-classes.',
            'filament.admin.resources.chart-of-accounts.',
            'filament.admin.resources.companies.',
        ];
    }
}
