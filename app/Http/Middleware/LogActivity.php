<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ActivityLogController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every authenticated POST / PUT / PATCH / DELETE request in activity_logs after the
 * response is built (failed requests are logged too, with their status code).
 * Login and logout are logged by the Auth event listeners in AppServiceProvider.
 */
class LogActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && Auth::check()) {
            ActivityLogController::record($request, $response);
        }

        return $response;
    }
}
