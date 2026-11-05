<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\PerfilPrestador;
use App\Models\Servicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Session\Store;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    /** Límites para no enviar listas sin tope al navegador (aún no hay paginación en la vista). */
    private const MAX_PEDIDOS = 50;
    private const MAX_CATALOGO = 100;
    private const MAX_NOTIFICACIONES = 20;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        // Valores por defecto: si algo falla, el dashboard igual se renderiza vacío.
        $datos = [
            'esPrestador'       => false,
            'perfil'            => null,
            'servicios'         => [],
            'pedidos'           => [],
            'misPedidos'        => [],
            'categorias'        => [],
            'todosLosServicios' => [],
            'notificaciones'    => [],
        ];

        try {
            // El perfil se consulta sin crearlo. Que exista un perfil NO convierte
            // a nadie en prestador: lo define el rol.
            $perfil = PerfilPrestador::where('user_id', $user->id)->first();
            $esPrestador = $user->hasRole('prestador') && $perfil !== null;

            $datos['esPrestador'] = $esPrestador;
            $datos['perfil'] = $perfil;

            if ($esPrestador) {
                $datos['servicios'] = Servicio::where('perfil_prestador_id', $perfil->id)
                    ->latest()
                    ->get();

                // Del cliente solo viajan id y nombre; nunca correo, teléfono ni códigos.
                $datos['pedidos'] = Pedido::whereHas(
                    'servicio',
                    fn ($q) => $q->where('perfil_prestador_id', $perfil->id)
                )
                    ->with(['cliente:id,nombre', 'servicio.categoria:id,nombre'])
                    ->latest()
                    ->limit(self::MAX_PEDIDOS)
                    ->get();
            } else {
                $datos['misPedidos'] = Pedido::where('cliente_id', $user->id)
                    ->with([
                        'servicio.categoria:id,nombre',
                        'servicio.perfilPrestador:id,user_id',
                        'servicio.perfilPrestador.user:id,nombre',
                        'resena',
                    ])
                    ->latest()
                    ->limit(self::MAX_PEDIDOS)
                    ->get();
            }

            // Catálogo: solo servicios activos de prestadores verificados (RF-08).
            $datos['todosLosServicios'] = Servicio::with([
                'categoria:id,nombre',
                'perfilPrestador:id,user_id',
                'perfilPrestador.user:id,nombre',
            ])
                ->where('activo', true)
                ->whereHas(
                    'perfilPrestador',
                    fn ($q) => $q->where('estado_verificacion', PerfilPrestador::ESTADO_VERIFICADO)
                )
                ->latest()
                ->limit(self::MAX_CATALOGO)
                ->get();

            $datos['categorias'] = Categoria::orderBy('nombre')->get();

            $datos['notificaciones'] = $user->unreadNotifications()
                ->latest()
                ->limit(self::MAX_NOTIFICACIONES)
                ->get();
        } catch (Throwable $e) {
            // Sin datos personales en el log: solo el ID y el motivo técnico.
            Log::error('Dashboard: no se pudieron cargar los datos.', [
                'user_id' => $user->id,
                'clase'   => $e::class,
                'error'   => $e->getMessage(),
            ]);

            $session = $request->session();
            $session->now('error', 'No pudimos cargar toda tu información. Intenta recargar la página.');
        }

        return Inertia::render('Dashboard', $datos);
    }
}