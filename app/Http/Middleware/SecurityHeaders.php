<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        if ($request->secure() || (bool) config('spims.force_https')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $frames = [
            "'self'",
            'https://player.vimeo.com',
            'https://www.youtube-nocookie.com',
        ];

        foreach ((array) config('spims.content.reading_embed_hosts', []) as $host) {
            $host = strtolower(trim((string) $host));
            if ($host !== '') {
                $frames[] = 'https://'.$host;
            }
        }

        return 'frame-src '.implode(' ', array_unique($frames));
    }
}
