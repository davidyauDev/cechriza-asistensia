<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogAuthHeader
{
    public function handle(Request $request, Closure $next)
    {
        $auth = $request->header('Authorization');
        $contentType = $request->header('Content-Type');

        Log::info('LogAuthHeader', [
            'uri' => $request->getRequestUri(),
            'method' => $request->getMethod(),
            'authorization' => $auth,
            'content_type' => $contentType,
            'all_headers' => $request->headers->all(),
        ]);

        return $next($request);
    }
}
