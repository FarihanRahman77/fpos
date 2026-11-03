<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Baseline browser security headers on every response: no framing by other sites (clickjacking),
 * no MIME sniffing, no full URLs (with ids / search terms) leaked in the Referer to other sites.
 * Headers already set by a controller are kept.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'Content-Security-Policy' => "frame-ancestors 'self'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=()',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (isset($response->headers)) {
            foreach (self::HEADERS as $name => $value) {
                if (!$response->headers->has($name)) {
                    $response->headers->set($name, $value);
                }
            }
            $response->headers->remove('X-Powered-By');
            if (!headers_sent()) {
                header_remove('X-Powered-By'); // set by PHP itself (expose_php)
            }
        }

        return $response;
    }
}
