<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\PerfilPrestador;
use App\Models\Resena;
use App\Notifications\EstadoVerificacionNotification;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerificacionPrestadorController extends Controller
{
    /**
     * Muestra la lista completa de prestadores con su estado de verificación.
     */
    public function index(Request $request): Response
    {
        $estado = $request->query('estado', 'pendiente');

        $relations = ['user'];
        if (method_exists(PerfilPrestador::class, 'documentos')) {
            $relations[] = 'documentos';
        }

        $prestadores = PerfilPrestador::with($relations)
            ->when($estado !== 'todos', function ($query) use ($estado) {
                return $query->where('estado_verificacion', $estado);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Prestadores/Index', [
            'prestadores'  => $prestadores,
            'filtroEstado' => $estado,
        ]);
    }

    /**
     * Ficha del prestador para el administrador: lo necesario para decidir
     * si aprueba o rechaza sin salir de la tabla a ciegas.
     *
     * Nunca se envía latitud/longitud: solo la ubicación aproximada en texto
     * y el radio de cobertura (misma regla que en el perfil público).
     */
    public function show(PerfilPrestador $perfil): Response
    {
        $perfil->load([
            'user:id,nombre,email',
            'servicios' => fn ($query) => $query
                ->select('id', 'perfil_prestador_id', 'categoria_id', 'titulo', 'precio_base', 'activo')
                ->with('categoria:id,nombre')
                ->orderBy('titulo'),
        ]);

        // Los pedidos y las reseñas cuelgan del servicio, no del perfil.
        $idsServicios = $perfil->servicios()->select('id');

        $conteoPedidos = Pedido::query()
            ->whereIn('servicio_id', $idsServicios)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        // Todos los estados siempre presentes (en 0 si no hay), para que la vista no tenga que adivinar.
        $pedidosPorEstado = collect(Pedido::ESTADOS)
            ->mapWithKeys(fn (string $estado) => [$estado => (int) ($conteoPedidos[$estado] ?? 0)]);

        $resenas = Resena::query()
            ->whereIn('servicio_id', $idsServicios)
            ->selectRaw('AVG(calificacion) as promedio, COUNT(*) as total')
            ->first();

        return Inertia::render('Admin/Prestadores/Perfil', [
            'perfil' => [
                'id'                   => $perfil->id,
                'descripcion'          => $perfil->descripcion,
                'ubicacion_aproximada' => $perfil->ubicacion_aproximada,
                'radio_cobertura_km'   => $perfil->radio_cobertura_km,
                'estado_verificacion'  => $perfil->estado_verificacion,
                'created_at'           => $perfil->created_at,
            ],
            'usuario' => [
                'nombre' => $perfil->user?->nombre,
                'email'  => $perfil->user?->email,
            ],
            'servicios' => $perfil->servicios->map(fn ($servicio) => [
                'id'          => $servicio->id,
                'titulo'      => $servicio->titulo,
                'precio_base' => $servicio->precio_base,
                'activo'      => $servicio->activo,
                'categoria'   => $servicio->categoria?->nombre,
            ])->values(),
            'pedidosPorEstado' => $pedidosPorEstado,
            'totalPedidos'     => $pedidosPorEstado->sum(),
            'resenas' => [
                'promedio' => $resenas?->promedio !== null ? round((float) $resenas->promedio, 1) : null,
                'total'    => (int) ($resenas?->total ?? 0),
            ],
        ]);
    }

    /**
     * Aprueba o rechaza la solicitud de verificación de un prestador.
     */
    public function updateStatus(Request $request, PerfilPrestador $perfil): RedirectResponse
    {
        $request->validate([
            'estado' => ['required', 'string', Rule::in(['aprobado', 'verificado', 'rechazado', 'pendiente'])],
        ]);

        // Mapeo defensivo: el frontend envía 'aprobado', pero la BD MySQL espera 'verificado'
        $estadoInput = $request->estado;
        if ($estadoInput === 'aprobado') {
            $nuevoEstado = 'verificado';
        } else {
            $nuevoEstado = $estadoInput;
        }

        // Validar que coincida con los valores reales del ENUM en MySQL
        if (!in_array($nuevoEstado, ['pendiente', 'verificado', 'rechazado'])) {
            return redirect()->back()->with('error', 'Estado de verificación no válido.');
        }

        // Actualizar ÚNICAMENTE la columna que existe en la BD
        // RF-52: el cambio y su rastro van juntos o no van.
        DB::transaction(function () use ($perfil, $nuevoEstado) {
            $anterior = $perfil->estado_verificacion;

            $perfil->update([
                'estado_verificacion' => $nuevoEstado,
            ]);

            Auditoria::registrar(
                'prestador.verificacion_cambiada',
                'perfil_prestador',
                $perfil->id,
                ['estado_verificacion' => $anterior],
                ['estado_verificacion' => $nuevoEstado],
            );
        });

        // Notificar al usuario prestador en la tabla 'notifications'
        if ($perfil->user) {
            $perfil->user->notify(new EstadoVerificacionNotification($perfil));
        }

        return redirect()->back()->with('success', "El estado del prestador ha sido actualizado a '{$nuevoEstado}'.");
    }
}