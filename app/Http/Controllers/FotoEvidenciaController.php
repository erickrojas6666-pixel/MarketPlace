<?php

namespace App\Http\Controllers;

use App\Models\FotoEvidencia;
use App\Models\Pedido;
use App\Policies\PedidoPolicy;
use App\Services\AlmacenFotos;
use App\Support\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Evidencia fotográfica de un pedido (antes / durante / después).
 *
 * Son fotos del interior de un domicilio: disco privado, nada de URLs públicas.
 * Cada lectura se autoriza y termina en una URL firmada de pocos minutos.
 */
class FotoEvidenciaController extends Controller
{
    /** Lista para el panel del pedido (JSON: lo consume el componente EvidenciaPedido). */
    public function index(Request $request, Pedido $pedido): JsonResponse
    {
        Gate::authorize('verEvidencias', $pedido);

        $user = $request->user();

        $fotos = $pedido->evidencias()->oldest()->get()->map(fn (FotoEvidencia $f) => [
            'id'             => $f->id,
            'fase'           => $f->fase,
            'pie'            => $f->pie,
            'autor'          => $f->subida_por === $pedido->cliente_id ? 'cliente' : 'prestador',
            'mia'            => $f->subida_por === $user->id,
            'puede_eliminar' => Gate::forUser($user)->allows('eliminar', $f),
            'url'            => route('evidencias.ver', ['foto' => $f->id]),
            'url_miniatura'  => route('evidencias.ver', ['foto' => $f->id, 'version' => 'miniatura']),
            'created_at'     => $f->created_at,
        ]);

        return response()->json([
            'puede_subir' => Gate::forUser($user)->allows('subirEvidencia', $pedido),
            'maximo'      => (int) config('marketplace.fotos.max_evidencias_por_parte'),
            'fotos'       => $fotos,
        ]);
    }

    public function store(Request $request, Pedido $pedido, AlmacenFotos $almacen): JsonResponse
    {
        Gate::authorize('subirEvidencia', $pedido);

        $request->validate([
            'foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('marketplace.fotos.max_kb')],
            'fase' => ['required', 'string', 'in:'.implode(',', FotoEvidencia::FASES)],
            'pie'  => ['nullable', 'string', 'max:160'],
        ]);

        $user = $request->user();
        $max  = (int) config('marketplace.fotos.max_evidencias_por_parte');

        $subidas = $pedido->evidencias()->where('subida_por', $user->id)->count();

        if ($subidas >= $max) {
            throw ValidationException::withMessages([
                'foto' => "Alcanzaste el máximo de {$max} fotos de evidencia para este pedido.",
            ]);
        }

        $datos = $almacen->guardar(
            $request->file('foto'),
            $almacen->discoPrivado(),
            "evidencia/{$pedido->id}",
        );

        try {
            $foto = DB::transaction(function () use ($pedido, $user, $datos, $request) {
                $foto = FotoEvidencia::create([
                    'pedido_id'   => $pedido->id,
                    'subida_por'  => $user->id,
                    'fase'        => $request->input('fase'),
                    'ruta'        => $datos['ruta'],
                    'hash_sha256' => $datos['hash_sha256'],
                    'pie'         => $request->input('pie') ?: null,
                ]);

                // RF-52. Nunca se registra la ruta ni el contenido: solo qué pasó y dónde.
                Auditoria::registrar('evidencia.subida', 'pedido', $pedido->id, null, [
                    'foto_id' => $foto->id,
                    'fase'    => $foto->fase,
                ]);

                return $foto;
            });
        } catch (\Throwable $e) {
            // Si no quedó registrada en BD, tampoco debe quedar el archivo huérfano.
            $almacen->borrar($almacen->discoPrivado(), $datos['ruta']);

            throw $e;
        }

        return response()->json(['id' => $foto->id], 201);
    }

    /** Autoriza y redirige a la URL firmada de la imagen (original o miniatura). */
    public function ver(Request $request, AlmacenFotos $almacen, FotoEvidencia $foto, string $version = 'original'): RedirectResponse
    {
        Gate::authorize('ver', $foto);

        $user   = $request->user();
        $pedido = $foto->pedido;

        // Quien no es parte del pedido (mediador) deja rastro de cada consulta.
        if (! PedidoPolicy::esCliente($user, $pedido) && ! PedidoPolicy::esPrestador($user, $pedido)) {
            Auditoria::registrar('evidencia.consultada', 'pedido', $pedido->id, null, ['foto_id' => $foto->id]);
        }

        $ruta = $foto->getAttributes()['ruta'];

        if ($version === 'miniatura') {
            $ruta = AlmacenFotos::miniaturaDe($ruta);
        }

        return redirect()->away($almacen->urlTemporal($ruta))
            ->header('Cache-Control', 'private, no-store');
    }

    public function destroy(FotoEvidencia $foto, AlmacenFotos $almacen): Response
    {
        Gate::authorize('eliminar', $foto);

        $ruta = $foto->getAttributes()['ruta'];

        DB::transaction(function () use ($foto) {
            Auditoria::registrar('evidencia.eliminada', 'pedido', $foto->pedido_id, null, [
                'foto_id' => $foto->id,
            ]);

            $foto->delete();
        });

        $almacen->borrar($almacen->discoPrivado(), $ruta);

        return response()->noContent();
    }
}
