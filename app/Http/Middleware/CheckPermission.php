<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\PermissionController;
use Closure;
use Illuminate\Http\Request;

/**
 * Role permissions (web group, after SetBranch and MainBranchOnly): the route name says which permission is needed
 * (PermissionController::ROUTE_MAP); the Superadmin role always passes. Routes not in the map are allowed and logged.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || !$request->route()) {
            return $next($request);
        }

        $name = (string) $request->route()->getName();
        $need = PermissionController::requiredFor($name, $request);

        if ($need === null) {
            if (str_starts_with($request->path(), 'admin') || $name !== '') {
                PermissionController::logUncovered($request);
            }
            return $next($request);
        }
        if ($need === [] || PermissionController::allows($need)) {
            return $next($request);
        }

        $message = PermissionController::deniedMessage($need);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['status' => false, 'message' => $message], 403);
        }

        // Someone without the dashboard lands on the first page they may open (e.g. POS for a cashier).
        $home = PermissionController::homeUrl();
        if ($name === 'dashboard' && $home !== route('dashboard')) {
            return redirect($home);
        }

        return response()->view('admin.no-permission', ['message' => $message, 'homeUrl' => $home], 403);
    }
}
