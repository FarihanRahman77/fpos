<?php

namespace App\Traits;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * For AJAX / JSON requests of a controller: an unexpected exception (SQL error, PHP error ...) is logged
 * and answered with a generic message, so raw SQL or exception text never reaches a toast — even with
 * APP_DEBUG on. Deliberate answers (abort(), validation, auth, CSRF) pass through unchanged.
 */
trait SafeJsonErrors
{
    public function callAction($method, $parameters)
    {
        try {
            return parent::callAction($method, $parameters);
        } catch (HttpExceptionInterface|ValidationException|AuthenticationException|AuthorizationException
            |HttpResponseException|ModelNotFoundException|TokenMismatchException $e) {
            throw $e;
        } catch (Throwable $e) {
            $request = request();
            if (!$request->expectsJson() && !$request->ajax()) {
                throw $e;
            }

            Log::error(static::class . '@' . $method . ' error', [
                'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'url' => $request->fullUrl(),
            ]);

            return response()->json(['status' => false, 'message' => 'Something went wrong. Please try again.'], 500);
        }
    }
}
