<?php

namespace App\Http\Controllers;

use App\Models\Mensaje;
use App\Models\Pedido;
use App\Models\User;
use App\Notifications\NuevoMensajeChat;
use App\Services\WebPushService;
use App\Support\Chat\AccesoChat;
use App\Support\Idempotencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Chat del pedido (RF-22): solo cliente y prestador, solo texto.
 *
 * Tiempo real por Web Push, no por consultas constantes: cada mensaje nuevo dispara un push a
 * la otra persona y el Service Worker avisa a la app abierta, que entonces pide "mensajes
 * después del id N". Esta ruta de lectura solo se consulta en ese momento, al abrir el chat y,
 * como red de seguridad, con poca frecuencia (más espaciada si no hay push disponible).
 * Enviar acepta Idempotency-Key, así que la cola offline puede reintentar sin duplicar.
 *
 * A quien no es parte del pedido se le responde 404 (no se revela que existe).
 */
class MensajeController extends Controller
{
    /** Máximo de mensajes por respuesta. */
    private const PAGINA = 100;

    /**
     * Sin parámetros: los últimos mensajes, en orden cronológico (primera carga).
     * Con ?despues=ID: solo los posteriores a ese id (lo que el cliente pide al recibir el push).
     */
    public function index(Request $request, Pedido $pedido): JsonResponse
    {
        $user = $request->user();

        abort_unless(AccesoChat::esParte($user, $pedido), 404);

        $filtros = $request->validate([
            'despues' => ['nullable', 'integer', 'min:0'],
        ]);

        $consulta = Mensaje::query()->where('pedido_id', $pedido->id);

        if (isset($filtros['despues'])) {
            $mensajes = $consulta->where('id', '>', $filtros['despues'])->orderBy('id')->limit(self::PAGINA)->get();
        } else {
            $mensajes = $consulta->orderByDesc('id')->limit(self::PAGINA)->get()->reverse()->values();
        }

        // Hasta qué mensaje mío ya leyó la otra persona (para mostrar "Visto").
        $leidosHasta = Mensaje::query()
            ->where('pedido_id', $pedido->id)
            ->where('emisor_id', $user->id)
            ->whereNotNull('leido_at')
            ->max('id');

        return response()->json([
            'puede_escribir' => AccesoChat::puedeEscribir($user, $pedido),
            'maximo'         => (int) config('marketplace.chat.max_caracteres'),
            // ¿Puede este servidor enviar push? El cliente lo combina con su propia suscripción
            // para decidir con qué frecuencia consulta.
            'push'           => app(WebPushService::class)->configurado(),
            'mensajes'       => $mensajes->map(fn (Mensaje $m) => $this->formato($m, $user))->values(),
            'leidos_hasta'   => $leidosHasta !== null ? (int) $leidosHasta : null,
        ]);
    }

    public function store(Request $request, Pedido $pedido): JsonResponse
    {
        $user = $request->user();

        abort_unless(AccesoChat::esParte($user, $pedido), 404);

        if (! AccesoChat::puedeEscribir($user, $pedido)) {
            return response()->json(['message' => 'Esta conversación está cerrada: el pedido ya terminó.'], 403);
        }

        $maximo = (int) config('marketplace.chat.max_caracteres');

        $datos = $request->validate([
            'contenido' => ['required', 'string', "max:{$maximo}"],
        ], [
            'contenido.required' => 'Escribe un mensaje.',
            'contenido.max'      => "El mensaje no puede pasar de {$maximo} caracteres.",
        ]);

        // Con la misma Idempotency-Key (cola offline) el mensaje se crea, y se avisa, una sola vez.
        $resultado = Idempotencia::ejecutar($request, 'chat.mensaje', function () use ($pedido, $user, $datos) {
            $mensaje = Mensaje::create([
                'pedido_id' => $pedido->id,
                'emisor_id' => $user->id,
                'contenido' => $datos['contenido'],
            ]);

            $this->avisarALaContraparte($pedido, $user);

            return ['mensaje' => $this->formato($mensaje, $user)];
        });

        $duplicado = $resultado['duplicado'] ?? false;

        return response()->json($resultado + ['duplicado' => false], $duplicado ? 200 : 201);
    }

    /** Marca como leídos los mensajes que la otra persona me envió en este pedido. */
    public function leidos(Request $request, Pedido $pedido): JsonResponse
    {
        $user = $request->user();

        abort_unless(AccesoChat::esParte($user, $pedido), 404);

        $marcados = Mensaje::query()
            ->where('pedido_id', $pedido->id)
            ->where('emisor_id', '!=', $user->id)
            ->whereNull('leido_at')
            ->update(['leido_at' => now()]);

        return response()->json(['marcados' => $marcados]);
    }

    /** Mensajes sin leer por pedido, para el contador del dashboard. */
    public function noLeidos(Request $request): JsonResponse
    {
        $user = $request->user();

        $porPedido = Mensaje::query()
            ->whereNull('leido_at')
            ->where('emisor_id', '!=', $user->id)
            ->whereHas('pedido', fn (Builder $pedido) => $pedido->where(
                fn (Builder $esMio) => $esMio
                    ->where('cliente_id', $user->id)
                    ->orWhereHas('servicio.perfilPrestador', fn (Builder $perfil) => $perfil->where('user_id', $user->id))
            ))
            ->selectRaw('pedido_id, COUNT(*) as total')
            ->groupBy('pedido_id')
            ->pluck('total', 'pedido_id')
            ->map(fn ($n) => (int) $n);

        return response()->json([
            'no_leidos' => (object) $porPedido->all(), // objeto JSON incluso vacío: {}
            'total'     => (int) $porPedido->sum(),
        ]);
    }

    /** Solo lo necesario: nada de ids de usuario, correos ni teléfonos. */
    private function formato(Mensaje $mensaje, User $user): array
    {
        return [
            'id'         => $mensaje->id,
            'mio'        => $mensaje->emisor_id === $user->id,
            'contenido'  => $mensaje->contenido,
            'leido'      => $mensaje->leido_at !== null,
            'created_at' => $mensaje->created_at?->toIso8601String(),
        ];
    }

    /**
     * Push a la otra persona en CADA mensaje: es lo que despierta su app para que lea lo nuevo
     * (si está a la vista se actualiza sin mostrar nada; si no, ve la notificación). Un fallo al
     * avisar nunca impide que el mensaje se guarde.
     */
    private function avisarALaContraparte(Pedido $pedido, User $emisor): void
    {
        try {
            AccesoChat::contraparte($emisor, $pedido)?->notify(new NuevoMensajeChat($pedido->id));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
