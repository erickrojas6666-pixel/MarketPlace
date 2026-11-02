<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\PerfilPrestador;
use App\Models\Servicio;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'metrics' => [
                'totalUsuarios' => User::count(),
                'totalPrestadores' => PerfilPrestador::count(),
                'prestadoresPendientes' => PerfilPrestador::where('estado_verificacion', 'pendiente')->count(),
                'totalServicios' => Servicio::count(),
                'totalPedidos' => Pedido::count(),
                'totalCategorias' => Categoria::count(),
            ],
            'prestadoresPendientesLista' => PerfilPrestador::with('user')
                ->where('estado_verificacion', 'pendiente')
                ->latest()
                ->take(5)
                ->get(),
            'ultimosPedidos' => Pedido::with(['cliente', 'servicio'])
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}