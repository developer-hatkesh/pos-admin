<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null && function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(app(CurrentCompany::class)->id());
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $next($request);
    }
}
