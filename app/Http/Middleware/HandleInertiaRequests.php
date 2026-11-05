<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

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
                    'roles'       => $user->getRoleNames(),
                    'esPrestador' => $user->hasRole('prestador'),
                    'esCliente'   => $user->hasRole('cliente'),
                    // Coincide con las rutas /admin (role:admin|superadmin|...).
                    'esAdmin'     => $user->hasAnyRole(['admin', 'superadmin']),
                ] : null,
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
