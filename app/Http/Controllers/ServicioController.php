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
    /**
     * Catálogo público de servicios.
     */
    public function index(Request $request): Response
    {
        $categoriaId = $request->query('categoria_id');
        $busqueda = $request->query('q');

        $servicios = Servicio::with(['categoria', 'perfilPrestador.user'])
            ->when($categoriaId, function ($query) use ($categoriaId) {
                return $query->where('categoria_id', $categoriaId);
            })
            ->when($busqueda, function ($query) use ($busqueda) {
                return $query->where('titulo', 'like', "%{$busqueda}%")
                             ->orWhere('descripcion', 'like', "%{$busqueda}%");
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $categorias = Categoria::orderBy('nombre', 'asc')->get();

        return Inertia::render('Servicios/Index', [
            'servicios'  => $servicios,
            'categorias' => $categorias,
            'filtros'    => [
                'categoria_id' => $categoriaId,
                'q'            => $busqueda,
            ],
        ]);
    }

    /**
     * Guarda un nuevo servicio asociándolo al perfil del prestador.
     */
    public function store(Request $request): RedirectResponse
    {
        $perfil = PerfilPrestador::where('user_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'titulo'       => 'required|string|max:255',
            'descripcion'  => 'required|string',
            'categoria_id' => 'required|exists:categorias,id',
            'precio'       => 'nullable|numeric|min:0',
        ]);

        Servicio::create([
            'perfil_prestador_id' => $perfil->id,
            'categoria_id'        => $validated['categoria_id'],
            'titulo'              => $validated['titulo'],
            'descripcion'         => $validated['descripcion'],
            'precio_base'         => $validated['precio'] ?? null,
            'activo'              => true,
        ]);

        return redirect()->back()->with('success', 'Servicio publicado correctamente.');
    }

    /**
     * Alterna el estado (activo/inactivo) del servicio.
     */
    public function toggleState(Servicio $servicio): RedirectResponse
    {
        $perfil = PerfilPrestador::where('user_id', Auth::id())->first();

        if (!$perfil || $servicio->perfil_prestador_id !== $perfil->id) {
            abort(403, 'Acción no autorizada.');
        }

        $servicio->update([
            'activo' => !$servicio->activo,
        ]);

        return redirect()->back()->with('success', 'Estado del servicio actualizado.');
    }
}