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
        
        // Buscar el perfil de prestador SIN crearlo automáticamente si no existe
        $perfil = PerfilPrestador::where('user_id', $user->id)->first();

        // Determinar si el usuario debe ver la vista de prestador
        $esPrestador = (method_exists($user, 'hasRole') && ($user->hasRole('prestador') || $user->hasRole('Prestador'))) 
            || (bool) ($user->esPrestador ?? false)
            || ($perfil !== null);

        $servicios = [];
        $pedidos = [];
        $misPedidos = [];

        if ($esPrestador && $perfil) {
            // Cargar servicios propios del prestador usando el ID de su perfil
            $servicios = Servicio::where('perfil_prestador_id', $perfil->id)->get();
            
            // Cargar pedidos recibidos para los servicios de este prestador
            $pedidos = Pedido::whereHas('servicio', function ($q) use ($perfil) {
                $q->where('perfil_prestador_id', $perfil->id);
            })->with(['cliente', 'servicio.categoria'])->latest()->get();

        } else {
            // Si es un cliente regular, cargar las solicitudes que ha hecho
            $misPedidos = Pedido::where('cliente_id', $user->id)
                ->with(['servicio.perfilPrestador.user', 'servicio.categoria', 'resena'])
                ->latest()
                ->get();
        }

        // Cargar catálogo de servicios activos disponible para contratación
        $todosLosServicios = Servicio::with(['categoria', 'perfilPrestador.user'])
            ->where('activo', true)
            ->latest()
            ->get();

        $notificaciones = $user->unreadNotifications;

        return Inertia::render('Dashboard', [
            'esPrestador'       => $esPrestador && $perfil !== null,
            'perfil'            => $perfil,
            'servicios'         => $servicios,
            'pedidos'           => $pedidos,
            'misPedidos'        => $misPedidos,
            'categorias'        => Categoria::orderBy('nombre', 'asc')->get(),
            'todosLosServicios' => $todosLosServicios,
            'notificaciones'    => $notificaciones,
        ]);
    }
}