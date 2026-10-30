<?php

use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PerfilPrestadorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\DashboardController;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\PerfilPrestador; 
use App\Models\Servicio;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Vista original de bienvenida de Breeze
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Ruta pública para el catálogo de servicios
Route::get('/servicios', [ServicioController::class, 'index'])->name('servicios.index');

// Dashboard unificado con lógica de roles (Cliente / Prestador)
Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Comunes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // SOLO prestador
    Route::middleware('role:prestador')->group(function () {
        Route::get('/prestador/perfil', [PerfilPrestadorController::class, 'edit'])->name('prestador.perfil.edit');
        Route::post('/prestador/perfil', [PerfilPrestadorController::class, 'update'])->name('prestador.perfil.update');
        Route::post('/servicios', [ServicioController::class, 'store'])->name('servicios.store');
        Route::patch('/servicios/{servicio}/toggle', [ServicioController::class, 'toggleState'])->name('servicios.toggle');
        Route::patch('/pedidos/{pedido}/estado', [PedidoController::class, 'updateStatus'])->name('pedidos.update-status');
    });

    // SOLO cliente
    Route::middleware('role:cliente')->group(function () {
        Route::post('/pedidos', [PedidoController::class, 'store'])->name('pedidos.store');
    });

    // SOLO admin
    Route::middleware('role:admin')->group(function () {
        Route::resource('categorias', CategoriaController::class)->except(['show', 'create', 'edit']);
    });
});

// Rutas protegidas por autenticación
Route::middleware('auth')->group(function () {
    // Perfil nativo de Breeze
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Perfil de prestador de servicios
    Route::get('/prestador/perfil', [PerfilPrestadorController::class, 'edit'])->name('prestador.perfil.edit');
    Route::post('/prestador/perfil', [PerfilPrestadorController::class, 'update'])->name('prestador.perfil.update');

    // Publicación y gestión de servicios (Prestador)
    Route::post('/servicios', [ServicioController::class, 'store'])->name('servicios.store');
    Route::patch('/servicios/{servicio}/toggle', [ServicioController::class, 'toggleState'])->name('servicios.toggle');

    // Solicitudes y pedidos (Cliente / Prestador)
    Route::post('/pedidos', [PedidoController::class, 'store'])->name('pedidos.store');
    Route::patch('/pedidos/{pedido}/estado', [PedidoController::class, 'updateStatus'])->name('pedidos.update-status');

    // Gestión de Categorías
    Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
    Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
    Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])->name('categorias.update');
    Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy'])->name('categorias.destroy');
});

require __DIR__.'/auth.php';