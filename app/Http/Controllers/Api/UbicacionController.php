<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class UbicacionController extends Controller
{
    /**
     * Proxy de geocodificación hacia Nominatim (OpenStreetMap).
     *
     * Nominatim limita el uso (~1 petición/seg) y bloquea abusos, por eso:
     * la ruta lleva throttle, la respuesta se cachea un día y hay timeout.
     */
    public function buscarUbicacion(Request $request): JsonResponse
    {
        $request->validate([
            'direccion' => 'required|string|min:3|max:255',
        ]);

        $direccion = trim($request->input('direccion'));
        $clave     = 'nominatim:'.md5(mb_strtolower($direccion));

        try {
            // Si la consulta falla se lanza excepción: Cache::remember NO guarda errores.
            $resultados = Cache::remember($clave, now()->addDay(), function () use ($direccion) {
                $response = Http::withHeaders([
                    'User-Agent' => 'OficiosYaApp/1.0 (contacto@oficiosya.com)', // Requerido por Nominatim
                ])
                    ->timeout(5)
                    ->retry(1, 200, throw: false)
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q'               => $direccion,
                        'format'          => 'json',
                        'addressdetails'  => 1,
                        'limit'           => 5,
                        'accept-language' => 'es',
                    ]);

                if ($response->failed()) {
                    throw new RuntimeException('Nominatim respondió '.$response->status());
                }

                return $response->json();
            });
        } catch (Throwable $e) {
            Log::warning('Geocodificación: Nominatim no disponible.', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'No se pudo consultar el servicio de ubicación.',
            ], 502);
        }

        return response()->json($resultados);
    }
}
