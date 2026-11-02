<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\UsuarioAdminController;
use App\Http\Controllers\Admin\VerificacionPrestadorController;
use App\Http\Controllers\Api\UbicacionController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PerfilPrestadorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResenaController;
use App\Http\Controllers\ServicioController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Vista pública de bienvenida
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'       => Route::has('login'),
        'canRegister'    => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'     => PHP_VERSION,
    ]);
});

// Catálogo público de servicios
Route::get('/servicios', [ServicioController::class, 'index'])->name('servicios.index');

Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
    $user = Auth::user();

    // Si el usuario es Admin / SuperAdmin, lo redirige directo a su panel
    if ($user && method_exists($user, 'hasRole') && $user->hasRole(['admin', 'superadmin', 'Admin', 'SuperAdmin'])) {
        return redirect()->route('admin.dashboard');
    }

    if ($user && ($user->is_admin ?? false)) {
        return redirect()->route('admin.dashboard');
    }

    // Para clientes y prestadores comunes, invoca el DashboardController pasándole el $request
    return app(DashboardController::class)($request);
})->middleware(['auth', 'verified'])->name('dashboard');
// --------------------------------------------------------------------------
// Rutas para Usuarios Autenticados (Clientes y Prestadores)
// --------------------------------------------------------------------------
Route::middleware('auth')->group(function () {

    // Notificaciones
    Route::post('/notificaciones/marcar-leidas', function () {
        Auth::user()->unreadNotifications->markAsRead();
        return redirect()->back()->with('success', 'Notificaciones marcadas como leídas.');
    })->name('notificaciones.marcarLeidas');

    // Perfil de Usuario
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // API de Geocodificación (Proxy Nominatim)
    Route::get('/api/buscar-ubicacion', [UbicacionController::class, 'buscarUbicacion'])->name('api.ubicacion');

    // Gestión de Pedidos
    Route::post('/pedidos', [PedidoController::class, 'store'])->name('pedidos.store');
    Route::patch('/pedidos/{pedido}/estado', [PedidoController::class, 'updateEstado'])->name('pedidos.updateEstado');

    // Publicación y Gestión de Servicios (Prestadores)
    Route::post('/servicios', [ServicioController::class, 'store'])->name('servicios.store');
    Route::patch('/servicios/{servicio}/toggle', [ServicioController::class, 'toggleState'])->name('servicios.toggle');

    // Reseñas y Calificaciones (Clientes)
    Route::post('/resenas', [ResenaController::class, 'store'])->name('resenas.store');

    // Perfil extendido de Prestador
    Route::get('/prestador/perfil', [PerfilPrestadorController::class, 'edit'])->name('prestador.perfil.edit');
    Route::post('/prestador/perfil', [PerfilPrestadorController::class, 'update'])->name('prestador.perfil.update');
});

// --------------------------------------------------------------------------
// Panel de Administración (SOLO Admin / SuperAdmin)
// --------------------------------------------------------------------------
Route::middleware(['auth', 'role:admin|superadmin|Admin|SuperAdmin'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {

        // Dashboard Admin
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Verificación de Prestadores
        Route::get('/prestadores', [VerificacionPrestadorController::class, 'index'])->name('prestadores.index');
        Route::patch('/prestadores/{perfil}/estado', [VerificacionPrestadorController::class, 'updateStatus'])->name('prestadores.updateStatus');

        // Gestión CRUD de Categorías
        Route::resource('categorias', CategoriaController::class)->except(['show', 'create', 'edit']);
        Route::resource('usuarios', UsuarioAdminController::class)->except(['create', 'edit', 'show']);
    });

require __DIR__.'/auth.php';