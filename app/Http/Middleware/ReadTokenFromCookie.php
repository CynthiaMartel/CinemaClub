<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ReadTokenFromCookie
{
    /**
     * Lee el token de autenticación desde la cookie HttpOnly 'auth_token'
     * y lo inyecta como cabecera Authorization: Bearer antes de que
     * Sanctum procese la petición.
     *
     * De este modo el token nunca es accesible desde JavaScript —
     * el navegador lo envía automáticamente en cada petición.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->hasCookie('auth_token') && !$request->bearerToken()) {
            $token = $request->cookie('auth_token');
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}
