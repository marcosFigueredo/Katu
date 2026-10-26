<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoStoreAuthenticatedResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $wasAuthenticated = $request->user() !== null;

        /** @var Response $response */
        $response = $next($request);

        if ($wasAuthenticated && $this->shouldPreventCache($request, $response)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
        }

        return $response;
    }

    private function shouldPreventCache(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type');

        return $contentType === '' || str_contains($contentType, 'text/html');
    }
}
