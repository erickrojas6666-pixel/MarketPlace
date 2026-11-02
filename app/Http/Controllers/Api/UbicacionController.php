<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UbicacionController extends Controller
{
    public function buscarUbicacion(Request $request)
    {
        $request->validate([
            'direccion' => 'required|string|min:3',
        ]);

        $direccion = $request->input('direccion');

        // Consumo de la API externa de Nominatim / OpenStreetMap
        $response = Http::withHeaders([
            'User-Agent' => 'OficiosYaApp/1.0 (contacto@oficiosya.com)' // Requerido por Nominatim
        ])->get('https://nominatim.openstreetmap.org/search', [
            'q' => $direccion,
            'format' => 'json',
            'addressdetails' => 1,
            'limit' => 5,
        ]);

        if ($response->failed()) {
            return response()->json([
                'error' => 'No se pudo consultar el servicio de ubicación.'
            ], $response->status());
        }

        return response()->json($response->json());
    }
}