<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attaches defensive HTTP response headers to every web response: anti-MIME-sniffing,
 * clickjacking protection, a tight referrer/permissions policy, HSTS over HTTPS, and a
 * Content-Security-Policy scoped to this app's own origin.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        // Only meaningful over TLS, and only send it there so an http:// dev host is never
        // pinned to https. One year, no preload/includeSubDomains to avoid over-committing.
        if ($request->secure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // Skip CSP only while the Vite dev server is running — its HMR client injects inline
        // scripts and opens a websocket to localhost that a same-origin CSP would block. With
        // built assets (npm run build) every asset is same-origin, so the policy applies.
        //
        // script-src is necessarily permissive: the views use inline <script> blocks and inline
        // on*= handlers (onsubmit="return confirm(...)", onchange="this.form.submit()") throughout,
        // and Alpine.js evaluates directive expressions via the Function constructor — so
        // 'unsafe-inline' and 'unsafe-eval' are both required for the UI to work. Tightening this
        // to nonces would mean refactoring every inline handler out first. Even so, the policy
        // still meaningfully constrains the origin: no external/object sources, and locked-down
        // base-uri, form-action, and frame-ancestors.
        if (! file_exists(public_path('hot'))) {
            $headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data:",
                "font-src 'self'",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
            ]));
        }

        return $response;
    }
}
