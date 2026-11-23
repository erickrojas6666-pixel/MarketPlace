<?php

namespace App\Http\Controllers;

use App\Models\SuscripcionesPush;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alta y baja de suscripciones Web Push del usuario autenticado.
 */
class PushController extends Controller
{
    private const MAX_DISPOSITIVOS = 5;

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'endpoint'    => ['required', 'url:https', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth'   => ['required', 'string', 'max:255'],
        ]);

        if (! WebPushService::hostPermitido($datos['endpoint'])) {
            throw ValidationException::withMessages([
                'endpoint' => 'Este navegador usa un servicio de notificaciones no admitido.',
            ]);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($datos, $usuario) {
            // Un dispositivo pertenece a una sola cuenta: si otra persona inicia sesión
            // en el mismo navegador, las notificaciones dejan de ir a la anterior.
            SuscripcionesPush::where('endpoint', $datos['endpoint'])
                ->where('user_id', '!=', $usuario->id)
                ->delete();

            SuscripcionesPush::updateOrCreate(
                ['user_id' => $usuario->id, 'endpoint' => $datos['endpoint']],
                [
                    'p256dh_key'         => $datos['keys']['p256dh'],
                    'auth_token_cifrado' => Crypt::encryptString($datos['keys']['auth']),
                ],
            );

            // Límite por usuario: se conservan los más recientes.
            $sobrantes = $usuario->suscripcionesPush()
                ->orderByDesc('id')
                ->skip(self::MAX_DISPOSITIVOS)
                ->take(100)
                ->pluck('id');

            if ($sobrantes->isNotEmpty()) {
                SuscripcionesPush::whereIn('id', $sobrantes)->delete();
            }
        });

        return response()->json(['ok' => true], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2048'],
        ]);

        $request->user()
            ->suscripcionesPush()
            ->where('endpoint', $datos['endpoint'])
            ->delete();

        return response()->json(['ok' => true]);
    }
}
