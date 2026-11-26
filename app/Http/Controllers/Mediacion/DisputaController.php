<?php

namespace App\Http\Controllers\Mediacion;

use App\Actions\Disputas\DisputaException;
use App\Actions\Disputas\ResolverDisputa;
use App\Actions\Disputas\TomarDisputa;
use App\Http\Controllers\Controller;
use App\Http\Resources\PagoResource;
use App\Models\Disputa;
use App\Models\Pago;
use App\Actions\Pagos\LiberarPago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * RF-38 — Panel del mediador: cola de disputas, ficha con la evidencia de ambas partes y
 * resolución. Las rutas ya exigen rol mediador o superadmin; DisputaPolicy::mediar además
 * excluye a quien sea parte del pedido.
 *
 * A la vista solo viajan nombres: nunca correos, teléfonos ni la dirección del domicilio.
 */
class DisputaController extends Controller
{
    private const POR_PAGINA = 15;

    private const RELACIONES = [
        'pedido:id,cliente_id,servicio_id,estado,fecha_programada',
        'pedido.cliente:id,nombre',
        'pedido.servicio:id,titulo,perfil_prestador_id',
        'pedido.servicio.perfilPrestador:id,user_id',
        'pedido.servicio.perfilPrestador.user:id,nombre',
        'mediador:id,nombre',
    ];

    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $vista   = $request->query('vista') === 'resueltas' ? 'resueltas' : 'pendientes';

        // La cola nunca incluye casos de los que el propio mediador es parte.
        $base = Disputa::query()->whereHas(
            'pedido',
            fn (Builder $pedido) => $pedido
                ->where('cliente_id', '!=', $usuario->id)
                ->whereDoesntHave(
                    'servicio.perfilPrestador',
                    fn (Builder $perfil) => $perfil->where('user_id', $usuario->id)
                )
        );

        $pendientes = (clone $base)->whereIn('estado', Disputa::ABIERTAS)->count();

        $consulta = (clone $base)->with(self::RELACIONES);

        $consulta = $vista === 'pendientes'
            ? $consulta->whereIn('estado', Disputa::ABIERTAS)->oldest()          // la más antigua primero
            : $consulta->whereNotIn('estado', Disputa::ABIERTAS)->latest('updated_at');

        $disputas = $consulta
            ->paginate(self::POR_PAGINA)
            ->withQueryString()
            ->through(fn (Disputa $d) => $this->resumen($d, $usuario->id))
            ->toArray();

        return Inertia::render('Mediacion/Index', [
            'disputas'   => $disputas,
            'pendientes' => $pendientes,
            'vista'      => $vista,
        ]);
    }

    public function show(Request $request, Disputa $disputa): Response
    {
        $usuario = $request->user();

        abort_unless($usuario->can('mediar', $disputa), 403);

        $disputa->load([...self::RELACIONES, 'pedido.servicio.categoria:id,nombre']);

        $pedido = $disputa->pedido;
        $pago   = Pago::with('transacciones')->where('pedido_id', $pedido->id)->first();

        return Inertia::render('Mediacion/Show', [
            'disputa' => [
                'id'               => $disputa->id,
                'estado'           => $disputa->estado,
                'motivo'           => $disputa->motivo,
                'resolucion_notas' => $disputa->resolucion_notas,
                'mediador'         => $disputa->mediador?->nombre,
                'mia'              => $disputa->mediador_id === $usuario->id,
                'abierta_en'       => $disputa->created_at?->toIso8601String(),
                'resuelta_en'      => $disputa->estaAbierta() ? null : $disputa->updated_at?->toIso8601String(),
            ],
            'pedido' => [
                'id'               => $pedido->id,
                'estado'           => $pedido->estado,
                'servicio'         => $pedido->servicio?->titulo,
                'categoria'        => $pedido->servicio?->categoria?->nombre,
                'cliente'          => $pedido->cliente?->nombre,
                'prestador'        => $pedido->servicio?->perfilPrestador?->user?->nombre,
                'fecha_programada' => $pedido->fecha_programada?->toIso8601String(),
            ],
            'pago'               => $pago ? $this->resumenPago($pago, $request) : null,
            // Las fotos se piden aparte (evidencias.index) con la misma autorización de siempre.
            'evidenciasVisibles' => $usuario->can('verEvidencias', $pedido),
            'puedeGestionar'     => $disputa->estaAbierta() && $disputa->puedeSerGestionadaPor($usuario),
        ]);
    }

    public function tomar(Request $request, Disputa $disputa, TomarDisputa $tomar): RedirectResponse
    {
        $usuario = $request->user();

        abort_unless($usuario->can('mediar', $disputa), 403);

        try {
            $tomar->ejecutar($usuario, $disputa->id);
        } catch (DisputaException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Tomaste el caso. Revisa la evidencia de ambas partes y decide.');
    }

    public function resolver(Request $request, Disputa $disputa, ResolverDisputa $resolver): RedirectResponse
    {
        $usuario = $request->user();

        abort_unless($usuario->can('mediar', $disputa), 403);

        $datos = $request->validate([
            'decision' => ['required', 'string', Rule::in([Disputa::FAVOR_CLIENTE, Disputa::FAVOR_PRESTADOR])],
            'notas'    => ['required', 'string', 'min:10', 'max:1500'],
        ], [
            'decision.required' => 'Elige a favor de quién se resuelve la disputa.',
            'decision.in'       => 'La decisión no es válida.',
            'notas.required'    => 'Explica brevemente el motivo de tu decisión.',
            'notas.min'         => 'Explica tu decisión con al menos :min caracteres: las dos partes la leerán.',
            'notas.max'         => 'Las notas no pueden pasar de :max caracteres.',
        ]);

        try {
            $resolver->ejecutar($usuario, $disputa->id, $datos['decision'], $datos['notas']);
        } catch (DisputaException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (ValidationException $e) {
            // El pago no se pudo liberar o reembolsar: no se movió nada.
            return redirect()->back()->with('error', $e->validator->errors()->first());
        } catch (HttpExceptionInterface $e) {
            // Solo los fallos de la pasarela (5xx) se muestran como aviso; el resto sigue su curso.
            if ($e->getStatusCode() < 500) {
                throw $e;
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('mediacion.disputas.index')
            ->with('success', 'Disputa resuelta. Avisamos a las dos partes.');
    }

    /** Fila de la cola. Solo nombres y un extracto del motivo. */
    private function resumen(Disputa $d, int $usuarioId): array
    {
        return [
            'id'         => $d->id,
            'pedido_id'  => $d->pedido_id,
            'estado'     => $d->estado,
            'servicio'   => $d->pedido?->servicio?->titulo,
            'cliente'    => $d->pedido?->cliente?->nombre,
            'prestador'  => $d->pedido?->servicio?->perfilPrestador?->user?->nombre,
            'motivo'     => Str::limit($d->motivo, 140),
            'mediador'   => $d->mediador?->nombre,
            'mia'        => $d->mediador_id === $usuarioId,
            'abierta_en' => $d->created_at?->toIso8601String(),
        ];
    }

    /**
     * Estado del escrow (misma regla que PagoResource) y reparto que haría una liberación.
     * Sin referencias de la pasarela ni movimientos internos.
     */
    private function resumenPago(Pago $pago, Request $request): array
    {
        $escrow  = (new PagoResource($pago))->resolve($request);
        $reparto = LiberarPago::desglose((float) $pago->monto);

        return [
            'estado'   => $escrow['estado'],
            'monto'    => (float) $pago->monto,
            'comision' => $reparto['comision'],
            'neto'     => $reparto['neto'],
            'metodo'   => $pago->metodo_pago,
        ];
    }
}
