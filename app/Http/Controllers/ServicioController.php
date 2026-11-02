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
     * Catálogo público de servicios (para el cliente).
     */
    public function index(): Response
    {
        $servicios = Servicio::with(['perfilPrestador.user', 'categoria'])
            ->where('activo', true)
            ->latest()
            ->get();

        return Inertia::render('Servicios/Index', [
            'servicios'  => $servicios,
            'categorias' => Categoria::all(),
        ]);
    }

    /**
     * Guardar un nuevo servicio publicado por un prestador.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $perfil = PerfilPrestador::where('user_id', $user->id)->first();

        if (!$perfil) {
            return redirect()->back()->with('error', 'No se encontró un perfil de prestador.');
        }

        $validated = $request->validate([
            'nombre'       => 'required|string|max:255',
            'descripcion'  => 'required|string|max:1000',
            'precio'       => 'nullable|numeric|min:0',
            'categoria_id' => 'required|exists:categorias,id',
        ]);

        Servicio::create([
            'perfil_prestador_id' => $perfil->id,
            'categoria_id'        => $validated['categoria_id'],
            'titulo'              => $validated['nombre'],      // La BD usa 'titulo'
            'descripcion'         => $validated['descripcion'],
            'precio_base'         => $validated['precio'] ?? 0, // La BD usa 'precio_base'
            'activo'              => true,
        ]);

        return redirect()->back()->with('success', '¡Servicio publicado con éxito!');
    }
    /**
     * Activar o desactivar la visibilidad de un servicio.
     */
    public function toggleState(Servicio $servicio): RedirectResponse
    {
        $user = Auth::user();

        // Verificar propiedad del servicio
        if ($servicio->perfilPrestador->user_id !== $user->id) {
            abort(403, 'No tienes permiso para modificar este servicio.');
        }

        $servicio->update([
            'activo' => !$servicio->activo,
        ]);

        $estadoTexto = $servicio->activo ? 'activado' : 'desactivado';
        return redirect()->back()->with('success', "Servicio {$estadoTexto} correctamente.");
    }
}