<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrivateNetworkAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->isMethod('OPTIONS')) {
            $response->headers->set('Access-Control-Allow-Private-Network', 'true');
        }
        return $response;
    }
}
