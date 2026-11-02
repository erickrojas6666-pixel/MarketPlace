<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PerfilPrestador;
use App\Notifications\EstadoVerificacionNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerificacionPrestadorController extends Controller
{
    /**
     * Muestra la lista completa de prestadores con su estado de verificación.
     */
    public function index(Request $request): Response
    {
        $estado = $request->query('estado', 'pendiente');

        $relations = ['user'];
        if (method_exists(PerfilPrestador::class, 'documentos')) {
            $relations[] = 'documentos';
        }

        $prestadores = PerfilPrestador::with($relations)
            ->when($estado !== 'todos', function ($query) use ($estado) {
                return $query->where('estado_verificacion', $estado);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Prestadores/Index', [
            'prestadores'  => $prestadores,
            'filtroEstado' => $estado,
        ]);
    }

    /**
     * Aprueba o rechaza la solicitud de verificación de un prestador.
     */
    public function updateStatus(Request $request, PerfilPrestador $perfil): RedirectResponse
    {
        $request->validate([
            'estado' => 'required|string',
        ]);

        // Mapeo defensivo: el frontend envía 'aprobado', pero la BD MySQL espera 'verificado'
        $estadoInput = $request->estado;
        if ($estadoInput === 'aprobado') {
            $nuevoEstado = 'verificado';
        } else {
            $nuevoEstado = $estadoInput;
        }

        // Validar que coincida con los valores reales del ENUM en MySQL
        if (!in_array($nuevoEstado, ['pendiente', 'verificado', 'rechazado'])) {
            return redirect()->back()->with('error', 'Estado de verificación no válido.');
        }

        // Actualizar ÚNICAMENTE la columna que existe en la BD
        $perfil->update([
            'estado_verificacion' => $nuevoEstado,
        ]);

        // Notificar al usuario prestador en la tabla 'notifications'
        if ($perfil->user) {
            $perfil->user->notify(new EstadoVerificacionNotification($perfil));
        }

        return redirect()->back()->with('success', "El estado del prestador ha sido actualizado a '{$nuevoEstado}'.");
    }
}