<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PerfilPrestador;
use Illuminate\Http\RedirectResponse;
use App\Notifications\EstadoVerificacionNotification;
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

        $prestadores = PerfilPrestador::with(['user', 'documentos'])
            ->when($estado !== 'todos', function ($query) use ($estado) {
                return $query->where('estado_verificacion', $estado);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Prestadores/Index', [
            'prestadores' => $prestadores,
            'filtroEstado' => $estado,
        ]);
    }

    /**
     * Aprueba o rechaza la solicitud de verificación de un prestador.
     */
    public function updateStatus(Request $request, PerfilPrestador $perfil): RedirectResponse
    {
        $request->validate([
            'estado' => 'required|in:aprobado,rechazado,pendiente',
            'motivo_rechazo' => 'required_if:estado,rechazado|nullable|string|max:500',
        ]);

        $perfil->update([
            'estado_verificacion' => $request->estado,
            'motivo_rechazo' => $request->estado === 'rechazado' ? $request->motivo_rechazo : null,
        ]);

        // Enviar notificación al usuario asociado al perfil
        if ($perfil->user) {
            $perfil->user->notify(new EstadoVerificacionNotification($perfil));
        }

        return redirect()->back()->with('success', "El estado del prestador ha sido actualizado a '{$request->estado}'.");
    }
}