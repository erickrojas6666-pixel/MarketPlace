<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\PerfilPrestador;
use App\Models\Servicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = Auth::user();
        
        // Comprobación defensiva del rol o perfil de prestador
        $esPrestador = (method_exists($user, 'hasRole') && ($user->hasRole('prestador') || $user->hasRole('Prestador'))) 
            || (bool) ($user->esPrestador ?? false)
            || PerfilPrestador::where('user_id', $user->id)->exists();

        $perfil = null;
        $servicios = [];
        $pedidos = [];
        $misPedidos = [];

        if ($esPrestador) {
            $perfil = PerfilPrestador::firstOrCreate(
                ['user_id' => $user->id],
                ['descripcion' => '', 'estado_verificacion' => 'pendiente']
            );

            $servicios = Servicio::where('perfil_prestador_id', $perfil->id)->get();
            
            $pedidos = Pedido::whereHas('servicio', function ($q) use ($perfil) {
                $q->where('perfil_prestador_id', $perfil->id);
            })->with(['cliente', 'servicio'])->latest()->get();

        } else {
            $misPedidos = Pedido::where('cliente_id', $user->id)
                ->with(['servicio', 'resena'])
                ->latest()
                ->get();
        }

        // Cargar servicios activos para el cliente
        $todosLosServicios = Servicio::with(['categoria', 'perfilPrestador.user'])
            ->where('activo', true)
            ->latest()
            ->get();

        $notificaciones = $user->unreadNotifications;

        return Inertia::render('Dashboard', [
            'esPrestador'       => $esPrestador,
            'perfil'            => $perfil,
            'servicios'         => $servicios,
            'pedidos'           => $pedidos,
            'misPedidos'        => $misPedidos,
            'categorias'        => Categoria::all(),
            'todosLosServicios' => $todosLosServicios,
            'notificaciones'    => $notificaciones,
        ]);
    }
}