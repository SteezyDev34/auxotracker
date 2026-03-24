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
        $allowedIps = array_filter(array_map('trim', explode(',', env('AUXOBOT_ALLOWED_IPS', '127.0.0.1,::1'))));

        // Vérifier le Bearer token
        $token = $request->bearerToken();
        if (!$token || !$expectedToken || !hash_equals((string) $expectedToken, (string) $token)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized token'], 403);
        }

        // Vérifier l'IP (prendre en compte X-Forwarded-For si présent)
        $remoteIp = $request->getClientIp();
        if ($remoteIp && !in_array($remoteIp, $allowedIps, true)) {
            return response()->json(['success' => false, 'error' => 'IP not allowed', 'ip' => $remoteIp], 403);
        }

        return $next($request);
    }
}
