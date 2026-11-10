<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\UsuarioAdminController;
use App\Http\Controllers\Admin\VerificacionPrestadorController;
use App\Http\Controllers\Api\UbicacionController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PagoRetornoController;
use App\Http\Controllers\PagoWebController;
use App\Http\Controllers\PerfilPrestadorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\ResenaController;
use App\Http\Controllers\ServicioController;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// --------------------------------------------------------------------------
// Rutas Públicas
// --------------------------------------------------------------------------
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

// --------------------------------------------------------------------------
// Dashboard Principal (Con Redirección Inteligente para Administradores)
// --------------------------------------------------------------------------
Route::get('/dashboard', function (Request $request) {
    $user = Auth::user();

    // Redirige al panel administrativo si tiene rol o marca de Admin / SuperAdmin
    if ($user && method_exists($user, 'hasRole') && $user->hasRole(['admin', 'superadmin', 'Admin', 'SuperAdmin'])) {
        return redirect()->route('admin.dashboard');
    }

    if ($user && ($user->is_admin ?? false)) {
        return redirect()->route('admin.dashboard');
    }

    // Para clientes y prestadores, carga el Dashboard regular
    return app(DashboardController::class)($request);
})->middleware(['auth', 'verified'])->name('dashboard');

// --------------------------------------------------------------------------
// Rutas para Usuarios Autenticados (Clientes y Prestadores)
// --------------------------------------------------------------------------
Route::middleware('auth')->group(function () {

    // Notificaciones del Sistema
    Route::post('/notificaciones/marcar-leidas', function () {
        Auth::user()->unreadNotifications->markAsRead();
        return redirect()->back()->with('success', 'Notificaciones marcadas como leídas.');
    })->name('notificaciones.marcarLeidas');

    // Notificaciones push (alta y baja de la suscripción de este dispositivo)
    Route::post('/push/suscripciones', [PushController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('push.suscribir');
    Route::delete('/push/suscripciones', [PushController::class, 'destroy'])
        ->middleware('throttle:20,1')
        ->name('push.desuscribir');

    // Perfil General de Usuario
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // API de Geocodificación (Proxy Nominatim)
    Route::get('/api/buscar-ubicacion', [UbicacionController::class, 'buscarUbicacion'])
        ->middleware('throttle:20,1')
        ->name('api.ubicacion');

    // Gestión de Pedidos y Estado
    Route::post('/pedidos', [PedidoController::class, 'store'])->name('pedidos.store');
    Route::patch('/pedidos/{pedido}/estado', [PedidoController::class, 'updateEstado'])->name('pedidos.updateEstado');

    // Pago del pedido (botón "Pagar") y regreso desde Mercado Pago
    Route::post('/pedidos/{pedido}/pagar', [PagoWebController::class, 'pagar'])
        ->middleware('throttle:20,1')
        ->name('pedidos.pagar');
    Route::get('/pagos/retorno', PagoRetornoController::class)->name('pagos.retorno');

    // Reseñas y Calificaciones (Clientes)
    Route::post('/resenas', [ResenaController::class, 'store'])->name('resenas.store');

    // Solo usuarios con rol prestador: perfil profesional y publicación de servicios.
    Route::middleware('role:prestador')->group(function () {
        Route::get('/prestador/perfil', [PerfilPrestadorController::class, 'edit'])->name('prestador.perfil.edit');
        Route::post('/prestador/perfil', [PerfilPrestadorController::class, 'update'])->name('prestador.perfil.update');

        Route::post('/servicios', [ServicioController::class, 'store'])->name('servicios.store');
        Route::patch('/servicios/{servicio}/toggle', [ServicioController::class, 'toggleState'])->name('servicios.toggle');
    });
});

// --------------------------------------------------------------------------
// Panel de Administración (SOLO Roles de Gestión)
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

        // CRUDs de Gestión
        Route::resource('categorias', CategoriaController::class)->except(['show', 'create', 'edit']);
        Route::resource('usuarios', UsuarioAdminController::class)->except(['show', 'create', 'edit']);
    });

require __DIR__.'/auth.php';