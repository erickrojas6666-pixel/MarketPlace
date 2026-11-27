<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\UsuarioAdminController;
use App\Http\Controllers\Admin\VerificacionPrestadorController;
use App\Http\Controllers\Api\UbicacionController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FotoEvidenciaController;
use App\Http\Controllers\FotoPerfilController;
use App\Http\Controllers\FotoTrabajoController;
use App\Http\Controllers\Mediacion\DisputaController;
use App\Http\Controllers\MensajeController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PedidoDisputaController;
use App\Http\Controllers\PagoRetornoController;
use App\Http\Controllers\PagoWebController;
use App\Http\Controllers\PerfilPrestadorController;
use App\Http\Controllers\VerificacionDocumentosController;
use App\Http\Controllers\Admin\DocumentoVerificacionController;
use App\Http\Controllers\PrestadorPublicoController;
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

// Perfil público del prestador (escaparate, RF-19): solo perfiles verificados.
Route::get('/prestadores/{perfil}', [PrestadorPublicoController::class, 'show'])
    ->whereNumber('perfil')
    ->name('prestadores.show');

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

    // El mediador no contrata ni presta servicios: su trabajo está en la cola de disputas.
    if ($user && $user->hasRole('mediador') && ! $user->hasAnyRole(['cliente', 'prestador'])) {
        return redirect()->route('mediacion.disputas.index');
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

    // Foto de perfil (cliente o prestador)
    Route::post('/perfil/foto', [FotoPerfilController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('perfil.foto.store');
    Route::delete('/perfil/foto', [FotoPerfilController::class, 'destroy'])
        ->name('perfil.foto.destroy');

    // Evidencia fotográfica del pedido (privada; lectura por URL firmada tras autorizar)
    Route::get('/pedidos/{pedido}/evidencias', [FotoEvidenciaController::class, 'index'])
        ->whereNumber('pedido')
        ->name('evidencias.index');
    Route::post('/pedidos/{pedido}/evidencias', [FotoEvidenciaController::class, 'store'])
        ->whereNumber('pedido')
        ->middleware('throttle:20,1')
        ->name('evidencias.store');
    Route::get('/evidencias/{foto}/{version?}', [FotoEvidenciaController::class, 'ver'])
        ->whereNumber('foto')
        ->where('version', 'original|miniatura')
        ->name('evidencias.ver');
    Route::delete('/evidencias/{foto}', [FotoEvidenciaController::class, 'destroy'])
        ->whereNumber('foto')
        ->name('evidencias.destroy');

    // Chat del pedido (RF-22): solo cliente y prestador. Se recibe por Web Push (el cliente consulta al avisarle el Service Worker); la cola offline
    // reenvía con Idempotency-Key lo escrito sin conexión.
    Route::get('/pedidos/{pedido}/mensajes', [MensajeController::class, 'index'])
        ->whereNumber('pedido')
        ->middleware('throttle:90,1')
        ->name('mensajes.index');
    Route::post('/pedidos/{pedido}/mensajes', [MensajeController::class, 'store'])
        ->whereNumber('pedido')
        ->middleware('throttle:30,1')
        ->name('mensajes.store');
    Route::post('/pedidos/{pedido}/mensajes/leidos', [MensajeController::class, 'leidos'])
        ->whereNumber('pedido')
        ->middleware('throttle:60,1')
        ->name('mensajes.leidos');
    Route::get('/chat/no-leidos', [MensajeController::class, 'noLeidos'])
        ->middleware('throttle:30,1')
        ->name('chat.noLeidos');

    // Gestión de Pedidos y Estado
    Route::post('/pedidos', [PedidoController::class, 'store'])->name('pedidos.store');
    Route::patch('/pedidos/{pedido}/estado', [PedidoController::class, 'updateEstado'])->name('pedidos.updateEstado');

    // Disputas (RF-38): el cliente reporta un problema con el trabajo completado
    Route::post('/pedidos/{pedido}/disputa', [PedidoDisputaController::class, 'store'])
        ->whereNumber('pedido')
        ->middleware('throttle:10,1')
        ->name('pedidos.disputa.store');

    // Pago del pedido (botón "Pagar") y regreso desde Mercado Pago
    Route::post('/pedidos/{pedido}/pagar', [PagoWebController::class, 'pagar'])
        ->middleware('throttle:20,1')
        ->name('pedidos.pagar');
    Route::get('/pagos/retorno', PagoRetornoController::class)->name('pagos.retorno');

    // Reseñas y Calificaciones (Clientes)
    Route::post('/resenas', [ResenaController::class, 'store'])->name('resenas.store');

    // Mediación de disputas (RF-38): ver la evidencia de ambas partes y resolver.
    // Solo mediador o superadmin; la política además excluye a quien sea parte del pedido.
    Route::middleware('role:mediador|superadmin')
        ->prefix('mediacion')
        ->as('mediacion.')
        ->group(function () {
            Route::get('/disputas', [DisputaController::class, 'index'])->name('disputas.index');
            Route::get('/disputas/{disputa}', [DisputaController::class, 'show'])
                ->whereNumber('disputa')
                ->name('disputas.show');
            Route::post('/disputas/{disputa}/tomar', [DisputaController::class, 'tomar'])
                ->whereNumber('disputa')
                ->middleware('throttle:30,1')
                ->name('disputas.tomar');
            Route::post('/disputas/{disputa}/resolver', [DisputaController::class, 'resolver'])
                ->whereNumber('disputa')
                ->middleware('throttle:20,1')
                ->name('disputas.resolver');
        });

    // Solo usuarios con rol prestador: perfil profesional y publicación de servicios.
    Route::middleware('role:prestador')->group(function () {
        Route::get('/prestador/perfil', [PerfilPrestadorController::class, 'edit'])->name('prestador.perfil.edit');
        Route::post('/prestador/perfil', [PerfilPrestadorController::class, 'update'])->name('prestador.perfil.update');

        // Verificación de identidad (RF-05, RF-06): INE y comprobante, cifrados en disco privado
        Route::get('/prestador/verificacion', [VerificacionDocumentosController::class, 'show'])
            ->name('prestador.verificacion.show');
        Route::post('/prestador/verificacion/documentos', [VerificacionDocumentosController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('prestador.verificacion.documentos.store');
        Route::get('/prestador/verificacion/documentos/{documento}', [VerificacionDocumentosController::class, 'ver'])
            ->whereNumber('documento')
            ->name('prestador.verificacion.documentos.ver');
        Route::delete('/prestador/verificacion/documentos/{documento}', [VerificacionDocumentosController::class, 'destroy'])
            ->whereNumber('documento')
            ->name('prestador.verificacion.documentos.destroy');
        Route::post('/prestador/verificacion/enviar', [VerificacionDocumentosController::class, 'enviar'])
            ->middleware('throttle:5,1')
            ->name('prestador.verificacion.enviar');

        Route::post('/servicios', [ServicioController::class, 'store'])->name('servicios.store');
        Route::patch('/servicios/{servicio}/toggle', [ServicioController::class, 'toggleState'])->name('servicios.toggle');

        // Portafolio: muestra de trabajos realizados de cada servicio
        Route::post('/servicios/{servicio}/fotos', [FotoTrabajoController::class, 'store'])
            ->whereNumber('servicio')
            ->middleware('throttle:30,1')
            ->name('servicios.fotos.store');
        Route::patch('/fotos-trabajo/{foto}', [FotoTrabajoController::class, 'update'])
            ->whereNumber('foto')
            ->name('fotos-trabajo.update');
        Route::delete('/fotos-trabajo/{foto}', [FotoTrabajoController::class, 'destroy'])
            ->whereNumber('foto')
            ->name('fotos-trabajo.destroy');
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
        Route::get('/prestadores/{perfil}', [VerificacionPrestadorController::class, 'show'])->name('prestadores.show');
        Route::patch('/prestadores/{perfil}/estado', [VerificacionPrestadorController::class, 'updateStatus'])->name('prestadores.updateStatus');

        // Revisión de documentos (RF-07, RF-49): motivo obligatorio y cada lectura auditada
        Route::post('/prestadores/{perfil}/documentos/acceso', [DocumentoVerificacionController::class, 'acceso'])
            ->whereNumber('perfil')
            ->middleware('throttle:30,1')
            ->name('documentos.acceso');
        Route::get('/documentos/{documento}', [DocumentoVerificacionController::class, 'ver'])
            ->whereNumber('documento')
            ->name('documentos.ver');
        Route::patch('/documentos/{documento}', [DocumentoVerificacionController::class, 'revisar'])
            ->whereNumber('documento')
            ->name('documentos.revisar');

        // CRUDs de Gestión
        Route::resource('categorias', CategoriaController::class)->except(['show', 'create', 'edit']);
        Route::resource('usuarios', UsuarioAdminController::class)->except(['show', 'create', 'edit']);
    });

require __DIR__.'/auth.php';