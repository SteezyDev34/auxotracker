<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuxobotMiddleware
{
    /**
     * Vérifie que la requête provient du bot autorisé via Bearer token et IP autorisée.
     */
    public function handle(Request $request, Closure $next)
    {
        $expectedToken = env('AUXOBOT_TOKEN');

        // Vérifier le Bearer token
        $token = $request->bearerToken();
        if (!$token || !$expectedToken || !hash_equals((string) $expectedToken, (string) $token)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized token'], 403);
        }

        return $next($request);
    }
}
