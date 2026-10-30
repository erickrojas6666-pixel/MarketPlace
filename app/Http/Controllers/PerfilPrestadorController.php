<?php

namespace App\Http\Controllers;

use App\Models\PerfilPrestador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PerfilPrestadorController extends Controller
{
    public function edit(): Response
    {
        $perfil = PerfilPrestador::where('user_id', Auth::id())->firstOrFail();

        return Inertia::render('Prestador/PerfilEdit', [
            'perfil' => $perfil,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'descripcion' => 'required|string|max:1000',
            'ubicacion_aproximada' => 'nullable|string|max:255',
        ]);

        $perfil = PerfilPrestador::where('user_id', Auth::id())->firstOrFail();
        $perfil->update([
            'descripcion' => $request->descripcion,
            'ubicacion_aproximada' => $request->ubicacion_aproximada,
        ]);

        return redirect()->back()->with('success', 'Perfil actualizado correctamente.');
    }
}