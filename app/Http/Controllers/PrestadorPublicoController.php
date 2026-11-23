<?php

namespace App\Http\Controllers;

use App\Models\FotoTrabajo;
use App\Models\Pedido;
use App\Models\PerfilPrestador;
use App\Models\Resena;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Perfil público del prestador, "escaparate" (RF-19): servicios, portafolio,
 * reseñas y verificación. Solo perfiles verificados; nunca coordenadas ni correo.
 */
class PrestadorPublicoController extends Controller
{
    private const MAX_GALERIA = 24;
    private const MAX_RESENAS = 10;

    public function show(PerfilPrestador $perfil): Response
    {
        abort_unless($perfil->estaVerificado(), 404);

        $perfil->load('user:id,nombre,foto_path');

        $servicios = $perfil->servicios()
            ->activos()
            ->with(['categoria:id,nombre', 'fotos'])
            ->orderBy('titulo')
            ->get();

        // Pedidos y reseñas cuelgan del servicio, no del perfil (incluye servicios hoy pausados).
        $idsServicios = $perfil->servicios()->select('id');

        $resumen = Resena::query()
            ->whereIn('servicio_id', $idsServicios)
            ->selectRaw('AVG(calificacion) as promedio, COUNT(*) as total')
            ->first();

        $resenas = Resena::query()
            ->whereIn('servicio_id', $idsServicios)
            ->with('cliente:id,nombre')
            ->latest()
            ->limit(self::MAX_RESENAS)
            ->get()
            ->map(fn (Resena $r) => [
                'id'           => $r->id,
                'calificacion' => $r->calificacion,
                'comentario'   => $r->comentario,
                'autor'        => $this->nombreAbreviado($r->cliente?->nombre),
                'fecha'        => $r->created_at?->toDateString(),
            ]);

        $galeria = FotoTrabajo::query()
            ->whereIn('servicio_id', $servicios->pluck('id'))
            ->with('servicio:id,titulo')
            ->orderBy('orden')
            ->orderByDesc('id')
            ->limit(self::MAX_GALERIA)
            ->get()
            ->map(fn (FotoTrabajo $f) => [
                'id'            => $f->id,
                'url'           => $f->url,
                'url_miniatura' => $f->url_miniatura,
                'pie'           => $f->pie,
                'servicio'      => $f->servicio?->titulo,
            ]);

        return Inertia::render('Prestadores/Show', [
            'prestador' => [
                'id'                   => $perfil->id,
                'nombre'               => $perfil->user?->nombre,
                'foto_url'             => $perfil->user?->foto_url,
                'descripcion'          => $perfil->descripcion,
                'ubicacion_aproximada' => $perfil->ubicacion_aproximada,
                'radio_cobertura_km'   => $perfil->radio_cobertura_km,
                'es_pro'               => (bool) $perfil->es_pro,
                'miembro_desde'        => $perfil->created_at?->toDateString(),
            ],
            'servicios' => $servicios->map(fn ($s) => [
                'id'          => $s->id,
                'titulo'      => $s->titulo,
                'descripcion' => $s->descripcion,
                'precio_base' => $s->precio_base !== null ? (float) $s->precio_base : null,
                'categoria'   => $s->categoria?->nombre,
                'portada'     => $s->fotos->first()?->url_miniatura,
            ])->values(),
            'galeria'  => $galeria,
            'resumen'  => [
                'promedio'           => $resumen?->promedio !== null ? round((float) $resumen->promedio, 1) : null,
                'total_resenas'      => (int) ($resumen?->total ?? 0),
                'trabajos_confirmados' => Pedido::query()
                    ->whereIn('servicio_id', $idsServicios)
                    ->where('estado', Pedido::CONFIRMADO)
                    ->count(),
            ],
            'resenas' => $resenas,
        ]);
    }

    /** "María López Pérez" → "María L." (las reseñas son públicas: no se publica el nombre completo). */
    private function nombreAbreviado(?string $nombre): string
    {
        $partes = preg_split('/\s+/', trim((string) $nombre)) ?: [];

        if ($partes === [] || $partes[0] === '') {
            return 'Cliente';
        }

        $inicial = isset($partes[1]) ? ' '.Str::upper(Str::substr($partes[1], 0, 1)).'.' : '';

        return $partes[0].$inicial;
    }
}
