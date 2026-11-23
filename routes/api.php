<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\BusquedaApiController;
use App\Http\Controllers\Api\PagoApiController;
use App\Http\Controllers\Api\PedidoApiController;
use App\Http\Controllers\Api\ResenaApiController;
use App\Http\Controllers\Api\ServicioApiController;
use App\Http\Controllers\Api\WebhookMercadoPagoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de OficiosYa (prefijo /api, autenticación por token Bearer — Sanctum)
|--------------------------------------------------------------------------
| 6 servicios propios: Autenticación, Catálogo, Búsqueda, Pedidos, Pagos y Reseñas.
| Documentación y ejemplos: docs/API.md
|
| Nota: /api/buscar-ubicacion (proxy de Nominatim, con sesión web) vive en routes/web.php.
*/

// 1. Autenticación
Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('login', [AuthApiController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthApiController::class, 'me'])->name('me');
        Route::post('logout', [AuthApiController::class, 'logout'])->name('logout');
    });
});

// Notificaciones de Mercado Pago (pública: se protege con firma y verificación contra su API).
Route::post('webhooks/mercadopago', [WebhookMercadoPagoController::class, 'recibir'])
    ->middleware('throttle:120,1')
    ->name('api.webhooks.mercadopago');

// 2, 3 y 6 (lectura). Públicos, igual que el catálogo web.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('servicios', [ServicioApiController::class, 'index'])->name('api.servicios.index');
    Route::get('servicios/{servicio}', [ServicioApiController::class, 'show'])
        ->whereNumber('servicio')
        ->name('api.servicios.show');

    Route::get('busqueda', [BusquedaApiController::class, 'index'])->name('api.busqueda');

    Route::get('resenas', [ResenaApiController::class, 'index'])->name('api.resenas.index');
});

// 4, 5 y 6 (escritura). Requieren token.
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

    // 5. Pagos (retención y liberación)
    Route::get('pagos', [PagoApiController::class, 'index'])->name('api.pagos.index');
    Route::post('pagos', [PagoApiController::class, 'store'])->name('api.pagos.store');
    Route::get('pagos/{pago}', [PagoApiController::class, 'show'])
        ->whereNumber('pago')
        ->name('api.pagos.show');
    Route::post('pagos/{pago}/liberar', [PagoApiController::class, 'liberar'])
        ->whereNumber('pago')
        ->name('api.pagos.liberar');

    // 6. Reseñas (crear)
    Route::post('resenas', [ResenaApiController::class, 'store'])->name('api.resenas.store');
});
