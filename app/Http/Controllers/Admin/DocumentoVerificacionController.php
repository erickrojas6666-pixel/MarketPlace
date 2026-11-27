<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentosVerificacion;
use App\Models\PerfilPrestador;
use App\Services\AlmacenFotos;
use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Revisión de documentos de identidad (RF-07, RF-49).
 *
 * Para ver un documento el revisor declara primero el MOTIVO (queda en auditoría) y recibe
 * una autorización temporal sobre ESE perfil. Cada lectura posterior también se registra.
 */
class DocumentoVerificacionController extends Controller
{
    private function llave(int $perfilId): string
    {
        return "acceso_documentos.{$perfilId}";
    }

    /** Autorización temporal vigente para este perfil → ['motivo' => ..., 'hasta' => timestamp] o null. */
    public static function accesoVigente(Request $request, int $perfilId): ?array
    {
        $acceso = $request->session()->get("acceso_documentos.{$perfilId}");

        return is_array($acceso) && ($acceso['hasta'] ?? 0) > now()->timestamp ? $acceso : null;
    }

    public function acceso(Request $request, PerfilPrestador $perfil): RedirectResponse
    {
        $validated = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:200'],
        ], [
            'motivo.required' => 'Indica el motivo por el que revisarás los documentos.',
            'motivo.min'      => 'Describe el motivo con al menos 5 caracteres.',
        ]);

        $minutos = (int) config('marketplace.verificacion.minutos_acceso_revisor');

        Auditoria::registrar('documentos.acceso_autorizado', 'perfil_prestador', $perfil->id, null, [
            'motivo'  => $validated['motivo'],
            'minutos' => $minutos,
        ]);

        $request->session()->put($this->llave($perfil->id), [
            'motivo' => $validated['motivo'],
            'hasta'  => now()->addMinutes($minutos)->timestamp,
        ]);

        return redirect()->back();
    }

    public function ver(Request $request, AlmacenFotos $almacen, DocumentosVerificacion $documento): Response
    {
        $acceso = self::accesoVigente($request, $documento->perfil_id);

        abort_if($acceso === null, 403, 'Declara el motivo de la revisión para ver los documentos.');
        abort_if($documento->purgado_at !== null || $documento->ruta_cifrada === '', 410, 'Documento eliminado por política de retención.');

        try {
            $bytes = $almacen->leerCifrado($almacen->discoPrivado(), $documento->ruta_cifrada, $documento->hash_sha256);
        } catch (\RuntimeException $e) {
            report($e);
            abort(404);
        }

        // RF-49: cada visualización queda registrada con su motivo.
        Auditoria::registrar('documento.consultado', 'perfil_prestador', $documento->perfil_id, null, [
            'documento_id' => $documento->id,
            'tipo'         => $documento->tipo,
            'motivo'       => $acceso['motivo'],
        ]);

        return response($bytes, 200, [
            'Content-Type'           => 'image/webp',
            'Content-Disposition'    => 'inline; filename="documento.webp"',
            'Cache-Control'          => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Marca un documento como válido o rechazado (con motivo). */
    public function revisar(Request $request, DocumentosVerificacion $documento): RedirectResponse
    {
        $validated = $request->validate([
            'estado' => ['required', 'in:valido,rechazado'],
            'motivo' => ['required_if:estado,rechazado', 'nullable', 'string', 'min:5', 'max:255'],
        ], [
            'motivo.required_if' => 'Indica por qué rechazas este documento (el prestador lo verá).',
        ]);

        abort_if($documento->purgado_at !== null, 410);

        DB::transaction(function () use ($documento, $validated, $request) {
            $anterior = $documento->estado;

            $documento->update([
                'estado'       => $validated['estado'],
                'motivo'       => $validated['estado'] === 'rechazado' ? $validated['motivo'] : null,
                'revisado_por' => $request->user()->id,
            ]);

            Auditoria::registrar('documento.revisado', 'perfil_prestador', $documento->perfil_id,
                ['estado' => $anterior],
                ['documento_id' => $documento->id, 'tipo' => $documento->tipo, 'estado' => $documento->estado],
            );
        });

        return redirect()->back()->with('success', 'Documento actualizado.');
    }
}
