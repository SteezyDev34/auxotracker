<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Pour toutes les requêtes API, ne pas rediriger
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }
        
        // Pour les requêtes web, essayer de rediriger vers login si la route existe
        try {
            return route('login');
        } catch (\Exception $e) {
            return null;
        }
    }
}
