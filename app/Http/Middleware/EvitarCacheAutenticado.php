<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las páginas de una sesión iniciada no deben quedar en la caché del navegador ni en su
 * caché de "atrás/adelante" (bfcache): si se guardaran, el botón Atrás las mostraría
 * después de cerrar sesión. Con no-store el navegador vuelve a pedirlas al servidor,
 * y ahí el middleware `auth` redirige al login.
 */
class EvitarCacheAutenticado
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        if ($request->user() !== null) {
            $respuesta->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $respuesta->headers->set('Pragma', 'no-cache');
            $respuesta->headers->set('Expires', '0');
        }

        return $respuesta;
    }
}
