<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\BusquedaApiController;
use App\Http\Controllers\Api\PagoApiController;
use App\Http\Controllers\Api\PedidoApiController;
use App\Http\Controllers\Api\ResenaApiController;
use App\Http\Controllers\Api\ServicioApiController;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('login', [AuthApiController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthApiController::class, 'me'])->name('me');
        Route::post('logout', [AuthApiController::class, 'logout'])->name('logout');
    });
});


Route::middleware('throttle:60,1')->group(function () {
    Route::get('servicios', [ServicioApiController::class, 'index'])->name('api.servicios.index');
    Route::get('servicios/{servicio}', [ServicioApiController::class, 'show'])
        ->whereNumber('servicio')
        ->name('api.servicios.show');

    Route::get('busqueda', [BusquedaApiController::class, 'index'])->name('api.busqueda');

    Route::get('resenas', [ResenaApiController::class, 'index'])->name('api.resenas.index');
});


Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // 4. Pedidos
    Route::get('pedidos', [PedidoApiController::class, 'index'])->name('api.pedidos.index');
    Route::post('pedidos', [PedidoApiController::class, 'store'])->name('api.pedidos.store');
    Route::get('pedidos/{pedido}', [PedidoApiController::class, 'show'])
        ->whereNumber('pedido')
        ->name('api.pedidos.show');
    Route::patch('pedidos/{pedido}/estado', [PedidoApiController::class, 'updateEstado'])
        ->whereNumber('pedido')
        ->name('api.pedidos.estado');

   
    Route::get('pagos', [PagoApiController::class, 'index'])->name('api.pagos.index');
    Route::post('pagos', [PagoApiController::class, 'store'])->name('api.pagos.store');
    Route::get('pagos/{pago}', [PagoApiController::class, 'show'])
        ->whereNumber('pago')
        ->name('api.pagos.show');
    Route::post('pagos/{pago}/liberar', [PagoApiController::class, 'liberar'])
        ->whereNumber('pago')
        ->name('api.pagos.liberar');

    Route::post('resenas', [ResenaApiController::class, 'store'])->name('api.resenas.store');
});
