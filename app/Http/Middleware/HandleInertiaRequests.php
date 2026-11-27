<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Con sesión iniciada, Inertia cifra el historial del navegador (las props de cada
     * página). Al cerrar sesión se invalida la clave (Inertia::clearHistory en el logout),
     * así que el botón Atrás ya no puede mostrar páginas privadas.
     */
    public function handle(Request $request, Closure $next)
    {
        Inertia::encryptHistory($request->user() !== null);

        return parent::handle($request, $next);
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id'          => $user->id,
                    'nombre'      => $user->nombre,
                    'email'       => $user->email,
                    'fotoUrl'     => $user->foto_url,
                    'roles'       => $user->getRoleNames(),
                    'esPrestador' => $user->hasRole('prestador'),
                    'esCliente'   => $user->hasRole('cliente'),
                    // Coincide con las rutas /admin (role:admin|superadmin|...).
                    'esAdmin'     => $user->hasAnyRole(['admin', 'superadmin']),
                    // Mediador o superadmin: ven la cola de disputas (/mediacion).
                    'esMediador'  => $user->hasAnyRole(['mediador', 'superadmin']),
                ] : null,
            ],

            'marketplace' => [
                'horasConfirmacion'   => (int) config('marketplace.horas_confirmacion_automatica'),
                // Sin esto el cliente solo puede confirmar el trabajo (no ve "Abrir disputa").
                'disputasHabilitadas' => (bool) config('marketplace.disputas_habilitadas'),
            ],

            // Lo lee Components/Toast.vue (page.props.flash).
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'status'  => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
