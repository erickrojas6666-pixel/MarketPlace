<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\PerfilPrestador;
use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PerfilPrestadorController extends Controller
{
    /**
     * Muestra la vista de edición del perfil del prestador y sus servicios.
     */
    public function edit(): Response
    {
        $userId = Auth::id();

        // Obtener o crear el perfil del prestador
        $perfil = PerfilPrestador::firstOrCreate(
            ['user_id' => $userId],
            ['estado_verificacion' => 'pendiente']
        );

        // Obtener las categorías
        $categorias = Categoria::orderBy('nombre', 'asc')->get();

        // Obtener los servicios usando perfil_prestador_id
        $serviciosPropios = Servicio::with('categoria')
            ->where('perfil_prestador_id', $perfil->id)
            ->latest()
            ->get();

        return Inertia::render('Prestador/PerfilEdit', [
            'perfil'           => $perfil,
            'categorias'       => $categorias,
            'serviciosPropios' => $serviciosPropios,
        ]);
    }

    /**
     * Actualiza la información del perfil del prestador.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'descripcion'          => 'required|string|max:1000',
            'ubicacion_aproximada' => 'nullable|string|max:255',
            'latitud'              => 'nullable|required_with:longitud|numeric|between:-90,90',
            'longitud'             => 'nullable|required_with:latitud|numeric|between:-180,180',
            'radio_cobertura_km'   => 'nullable|integer|between:1,100',
        ]);

        // ~110 m de precisión: sirve para filtrar por cercanía sin revelar el domicilio exacto.
        $latitud  = isset($validated['latitud']) ? round((float) $validated['latitud'], 3) : null;
        $longitud = isset($validated['longitud']) ? round((float) $validated['longitud'], 3) : null;

        $perfil = PerfilPrestador::firstOrCreate(
            ['user_id' => Auth::id()],
            ['estado_verificacion' => 'pendiente']
        );

        $perfil->update([
            'descripcion'          => $validated['descripcion'],
            'ubicacion_aproximada' => $validated['ubicacion_aproximada'] ?? null,
            'latitud'              => $latitud,
            'longitud'             => $longitud,
            'radio_cobertura_km'   => $validated['radio_cobertura_km'] ?? 10,
        ]);

        return redirect()->back()->with('success', 'Perfil actualizado correctamente.');
    }
}