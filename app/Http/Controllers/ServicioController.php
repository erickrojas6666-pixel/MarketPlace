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

class ServicioController extends Controller
{
    // Catálogo público
    public function index(): Response
    {
        return Inertia::render('Servicios/Index', [
            'categorias' => Categoria::all(),
            'servicios' => Servicio::with(['perfilesPrestador.user', 'categoria'])
                ->where('activo', true)
                ->latest()
                ->get(),
        ]);
    }

    // Guardar nuevo servicio
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'categoria_id' => 'required|exists:categorias,id',
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'precio_base' => 'nullable|numeric|min:0',
        ]);

        $perfil = PerfilPrestador::where('user_id', Auth::id())->firstOrFail();

        Servicio::create([
            'perfil_prestador_id' => $perfil->id,
            'categoria_id' => $request->categoria_id,
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'precio_base' => $request->precio_base,
            'activo' => true,
        ]);

        return redirect()->route('dashboard')->with('success', 'Servicio publicado con éxito.');
    }

    // Cambiar estado activo/inactivo
    public function toggleState(Servicio $servicio): RedirectResponse
    {
        $perfil = PerfilPrestador::where('user_id', Auth::id())->firstOrFail();

        if ($servicio->perfil_prestador_id !== $perfil->id) {
            abort(403);
        }

        $servicio->update(['activo' => !$servicio->activo]);

        return redirect()->back();
    }
}