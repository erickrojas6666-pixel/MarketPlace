<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\PerfilPrestador;
use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Prestador de servicios
        if ($user->hasRole('prestador')) {
            return $this->dashboardPrestador($user);
        }

        // Cliente (o admin)
        return $this->dashboardCliente($user);
    }

    private function dashboardPrestador($user): Response
    {
        // Crea el perfil si el rol se asignó directamente en la BD
        $perfil = PerfilPrestador::firstOrCreate(
            ['user_id' => $user->id],
            ['descripcion' => '', 'estado_verificacion' => 'pendiente']
        );

        $servicios = Servicio::where('perfil_prestador_id', $perfil->id)->get();

        $pedidos = Pedido::whereIn('servicio_id', $servicios->pluck('id'))
            ->with(['cliente', 'servicio'])
            ->latest()
            ->get();

        return Inertia::render('Dashboard', [
            'perfil'     => $perfil,
            'servicios'  => $servicios,
            'pedidos'    => $pedidos,
            'categorias' => Categoria::all(),
        ]);
    }

    private function dashboardCliente($user): Response
    {
        $misPedidos = Pedido::where('cliente_id', $user->id)
            ->with('servicio')
            ->latest()
            ->get();

        return Inertia::render('Dashboard', [
            'categorias' => Categoria::all(),
            'misPedidos' => $misPedidos,
        ]);
    }
}