<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Clickjacking: impide que la app se incruste en iframes de otros dominios
        $response->headers->set('X-Frame-Options', 'DENY');

        // MIME sniffing: el navegador no "adivina" el tipo de archivo (vector de XSS)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Controla qué URL se envía como Referer en navegación entre dominios
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Desactiva APIs de hardware que la app no usa
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Forzar HTTPS solo en producción para no romper el entorno local
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Content Security Policy — equilibrio entre seguridad y compatibilidad
        // con Vue SPA, CKEditor 5, Tailwind inline-styles e imágenes externas (TMDB, Cloudinary)
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com", // unsafe-eval requerido por CKEditor 5; Cloudflare Turnstile
            "style-src 'self' 'unsafe-inline'",                // inline styles de Tailwind/Vue
            "img-src 'self' data: blob: https:",               // posters de TMDB/Cloudinary
            "font-src 'self' data:",
            "connect-src 'self' https://challenges.cloudflare.com",  // Turnstile necesita comunicarse con Cloudflare
            "frame-src 'self' https://challenges.cloudflare.com",    // Turnstile se renderiza en un iframe de Cloudflare
            "frame-ancestors 'none'",                          // equivalente moderno de X-Frame-Options
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
