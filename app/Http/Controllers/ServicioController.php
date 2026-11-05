<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\PerfilPrestador;
use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServicioController extends Controller
{
    /** Radios de búsqueda que el cliente puede elegir (km). */
    private const RADIOS_KM = [5, 10, 25, 50];

    private const RADIO_POR_DEFECTO_KM = 10;

    /**
     * Catálogo público de servicios.
     */
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'categoria_id' => 'nullable|integer',
            'q'            => 'nullable|string|max:100',
            // Ubicación del cliente: viaja solo en la búsqueda, no se guarda.
            'lat'          => 'nullable|required_with:lng|numeric|between:-90,90',
            'lng'          => 'nullable|required_with:lat|numeric|between:-180,180',
            'radio_km'     => 'nullable|integer|in:'.implode(',', self::RADIOS_KM),
        ]);

        $categoriaId = $filtros['categoria_id'] ?? null;
        $busqueda    = $filtros['q'] ?? null;
        $lat         = isset($filtros['lat']) ? (float) $filtros['lat'] : null;
        $lng         = isset($filtros['lng']) ? (float) $filtros['lng'] : null;
        $radioKm     = (int) ($filtros['radio_km'] ?? self::RADIO_POR_DEFECTO_KM);
        $porCercania = $lat !== null && $lng !== null;

        // Catálogo público: solo servicios activos de prestadores verificados.
        // Del prestador solo viajan id y nombre (nunca correo ni otros datos).
        $servicios = Servicio::query()
            ->with([
                'categoria:id,nombre',
                'perfilPrestador:id,user_id',
                'perfilPrestador.user:id,nombre',
            ])
            ->activos()
            ->whereHas(
                'perfilPrestador',
                fn ($q) => $q->where('estado_verificacion', PerfilPrestador::ESTADO_VERIFICADO)
            )
            ->when($categoriaId, fn ($q) => $q->where('categoria_id', $categoriaId))
            // Con ubicación: solo prestadores que atienden ese punto, del más cercano al más lejano.
            ->when($porCercania, fn ($q) => $q->cercanosA($lat, $lng, $radioKm))
            // El grupo evita que el orWhere anule el filtro de categoría y de activos.
            ->when($busqueda, fn ($q) => $q->where(function ($w) use ($busqueda) {
                $w->where('titulo', 'like', "%{$busqueda}%")
                  ->orWhere('descripcion', 'like', "%{$busqueda}%");
            }))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Servicios/Index', [
            'servicios'  => $servicios,
            'categorias' => Categoria::orderBy('nombre')->get(['id', 'nombre', 'descripcion']),
            'filtros'    => [
                'categoria_id' => $categoriaId,
                'q'            => $busqueda,
                'lat'          => $lat,
                'lng'          => $lng,
                'radio_km'     => $radioKm,
            ],
        ]);
    }

    /**
     * Guarda un nuevo servicio asociándolo al perfil del prestador.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Solo prestadores con perfil verificado pueden publicar (RF-08).
        if (! $user->esPrestadorVerificado()) {
            return redirect()->back()->with('error', 'Tu perfil debe estar verificado para publicar servicios.');
        }

        $perfil = $user->perfilPrestador;

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
    public function toggleState(Request $request, Servicio $servicio): RedirectResponse
    {
        $perfil = $request->user()->perfilPrestador;

        abort_unless(
            $perfil && (int) $servicio->perfil_prestador_id === (int) $perfil->id,
            403,
            'Acción no autorizada.'
        );

        $servicio->update([
            'activo' => !$servicio->activo,
        ]);

        return redirect()->back()->with('success', 'Estado del servicio actualizado.');
    }
}