<?php

namespace App\Http\Controllers;

use App\Models\DocumentosVerificacion;
use App\Models\PerfilPrestador;
use App\Services\AlmacenFotos;
use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Verificación de identidad del prestador (RF-05, RF-06): INE (frente y reverso) y
 * comprobante de domicilio. Los archivos se guardan cifrados en el disco privado.
 */
class VerificacionDocumentosController extends Controller
{
    private function perfil(Request $request): PerfilPrestador
    {
        return PerfilPrestador::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['estado_verificacion' => PerfilPrestador::ESTADO_PENDIENTE],
        );
    }

    private function requeridos(): array
    {
        return (array) config('marketplace.verificacion.tipos_requeridos');
    }

    public function show(Request $request): InertiaResponse
    {
        $perfil = $this->perfil($request);

        $docs = $perfil->documentos()->get()->keyBy('tipo');

        $documentos = collect($this->requeridos())->map(function (string $tipo) use ($docs) {
            /** @var DocumentosVerificacion|null $d */
            $d = $docs->get($tipo);

            return [
                'tipo'      => $tipo,
                'id'        => $d?->id,
                'estado'    => $d?->estado,        // pendiente | valido | rechazado | null (sin subir)
                'motivo'    => $d?->motivo,
                'purgado'   => $d?->purgado_at !== null,
                'subido_en' => $d?->updated_at,
            ];
        })->values();

        $completo = $documentos->every(fn ($d) => $d['id'] !== null && ! $d['purgado'] && $d['estado'] !== 'rechazado');

        return Inertia::render('Prestador/Verificacion', [
            'estado'           => $perfil->estadoVisible(),
            'motivoRechazo'    => $perfil->estado_verificacion === PerfilPrestador::ESTADO_RECHAZADO ? $perfil->motivo_rechazo : null,
            'puedeEditar'      => $perfil->puedeEditarDocumentos(),
            'documentos'       => $documentos,
            'completo'         => $completo,
            'enviadaEn'        => $perfil->verificacion_enviada_at,
            'retencionDias'    => (int) config('marketplace.verificacion.retencion_dias'),
        ]);
    }

    public function store(Request $request, AlmacenFotos $almacen): RedirectResponse
    {
        $perfil = $this->perfil($request);

        $request->validate([
            'tipo' => ['required', 'string', 'in:'.implode(',', $this->requeridos())],
            'foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('marketplace.fotos.max_kb')],
        ]);

        if (! $perfil->puedeEditarDocumentos()) {
            throw ValidationException::withMessages([
                'foto' => 'Tu solicitud ya está en revisión o fue aprobada: no se pueden cambiar documentos.',
            ]);
        }

        $tipo = $request->input('tipo');

        $datos = $almacen->guardarCifrado(
            $request->file('foto'),
            $almacen->discoPrivado(),
            "verificacion/{$perfil->id}",
            (int) config('marketplace.verificacion.lado_max'),
        );

        $rutaAnterior = null;

        try {
            DB::transaction(function () use ($perfil, $tipo, $datos, &$rutaAnterior) {
                $anterior = $perfil->documentos()->where('tipo', $tipo)->first();
                $rutaAnterior = $anterior && $anterior->ruta_cifrada !== '' ? $anterior->ruta_cifrada : null;

                $anterior?->delete();

                $doc = DocumentosVerificacion::create([
                    'perfil_id'    => $perfil->id,
                    'tipo'         => $tipo,
                    'ruta_cifrada' => $datos['ruta'],
                    'hash_sha256'  => $datos['hash_sha256'],
                    'estado'       => 'pendiente',
                ]);

                Auditoria::registrar('verificacion.documento_subido', 'perfil_prestador', $perfil->id, null, [
                    'documento_id' => $doc->id,
                    'tipo'         => $tipo,
                ]);
            });
        } catch (\Throwable $e) {
            $almacen->borrar($almacen->discoPrivado(), $datos['ruta']);

            throw $e;
        }

        $almacen->borrar($almacen->discoPrivado(), $rutaAnterior);

        return redirect()->back()->with('success', 'Documento guardado de forma segura.');
    }

    /** El prestador puede ver sus propios documentos para comprobar que se leen bien. */
    public function ver(Request $request, AlmacenFotos $almacen, DocumentosVerificacion $documento): Response
    {
        $perfil = $this->perfil($request);

        abort_unless($documento->perfil_id === $perfil->id, 403);
        abort_if($documento->purgado_at !== null || $documento->ruta_cifrada === '', 410, 'Documento eliminado por política de retención.');

        try {
            $bytes = $almacen->leerCifrado($almacen->discoPrivado(), $documento->ruta_cifrada, $documento->hash_sha256);
        } catch (\RuntimeException $e) {
            report($e);
            abort(404);
        }

        return response($bytes, 200, [
            'Content-Type'           => 'image/webp',
            'Content-Disposition'    => 'inline; filename="documento.webp"',
            'Cache-Control'          => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, AlmacenFotos $almacen, DocumentosVerificacion $documento): RedirectResponse
    {
        $perfil = $this->perfil($request);

        abort_unless($documento->perfil_id === $perfil->id, 403);

        if (! $perfil->puedeEditarDocumentos()) {
            throw ValidationException::withMessages([
                'foto' => 'Tu solicitud ya está en revisión o fue aprobada: no se pueden eliminar documentos.',
            ]);
        }

        $ruta = $documento->ruta_cifrada !== '' ? $documento->ruta_cifrada : null;

        DB::transaction(function () use ($perfil, $documento) {
            Auditoria::registrar('verificacion.documento_eliminado', 'perfil_prestador', $perfil->id, null, [
                'documento_id' => $documento->id,
                'tipo'         => $documento->tipo,
            ]);

            $documento->delete();
        });

        $almacen->borrar($almacen->discoPrivado(), $ruta);

        return redirect()->back()->with('success', 'Documento eliminado.');
    }

    /** Envía la solicitud a revisión (RF-06). Exige el aviso de privacidad aceptado. */
    public function enviar(Request $request): RedirectResponse
    {
        $perfil = $this->perfil($request);

        $request->validate([
            'acepta_aviso' => ['accepted'],
        ], [
            'acepta_aviso.accepted' => 'Debes aceptar el aviso de privacidad para enviar tus documentos.',
        ]);

        if (! $perfil->puedeEditarDocumentos()) {
            throw ValidationException::withMessages(['acepta_aviso' => 'Tu solicitud ya fue enviada.']);
        }

        $docs = $perfil->documentos()->get()->keyBy('tipo');

        foreach ($this->requeridos() as $tipo) {
            $d = $docs->get($tipo);

            if (! $d || $d->purgado_at !== null || $d->estado === 'rechazado') {
                throw ValidationException::withMessages([
                    'acepta_aviso' => 'Falta subir (o volver a subir) todos los documentos requeridos.',
                ]);
            }
        }

        DB::transaction(function () use ($perfil) {
            $anterior = $perfil->estado_verificacion;

            $perfil->update([
                'estado_verificacion'     => PerfilPrestador::ESTADO_PENDIENTE,
                'verificacion_enviada_at' => now(),
                'motivo_rechazo'          => null,
            ]);

            Auditoria::registrar('prestador.verificacion_enviada', 'perfil_prestador', $perfil->id,
                ['estado_verificacion' => $anterior],
                ['estado_verificacion' => PerfilPrestador::ESTADO_PENDIENTE, 'aviso_privacidad_aceptado' => true],
            );
        });

        return redirect()->back()->with('success', 'Documentos enviados. Te avisaremos cuando terminemos la revisión.');
    }
}
