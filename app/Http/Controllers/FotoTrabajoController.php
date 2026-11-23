<?php

namespace App\Http\Controllers;

use App\Models\FotoTrabajo;
use App\Models\Servicio;
use App\Services\AlmacenFotos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Portafolio público de un servicio: muestra de trabajos realizados (RF-10, RF-19). */
class FotoTrabajoController extends Controller
{
    public function store(Request $request, Servicio $servicio, AlmacenFotos $almacen): RedirectResponse
    {
        Gate::authorize('gestionarFotos', $servicio);

        $request->validate([
            'foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('marketplace.fotos.max_kb')],
            'pie'  => ['nullable', 'string', 'max:160'],
        ]);

        $max = (int) config('marketplace.fotos.max_por_servicio');

        if ($servicio->fotos()->count() >= $max) {
            throw ValidationException::withMessages([
                'foto' => "Cada servicio admite hasta {$max} fotos. Elimina alguna para subir otra.",
            ]);
        }

        $datos = $almacen->guardar(
            $request->file('foto'),
            $almacen->discoPublico(),
            "portafolio/{$servicio->id}",
        );

        FotoTrabajo::create([
            'servicio_id' => $servicio->id,
            'ruta'        => $datos['ruta'],
            'hash_sha256' => $datos['hash_sha256'],
            'pie'         => $request->input('pie') ?: null,
            'orden'       => (int) $servicio->fotos()->max('orden') + 1,
        ]);

        return redirect()->back()->with('success', 'Foto agregada a tu portafolio.');
    }

    /** Cambia el pie de foto o la marca como portada (primera del portafolio). */
    public function update(Request $request, FotoTrabajo $foto): RedirectResponse
    {
        $servicio = $foto->servicio;
        Gate::authorize('gestionarFotos', $servicio);

        $validated = $request->validate([
            'pie'     => ['nullable', 'string', 'max:160'],
            'portada' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($foto, $servicio, $validated) {
            if (array_key_exists('pie', $validated)) {
                $foto->update(['pie' => $validated['pie'] ?: null]);
            }

            if (! empty($validated['portada'])) {
                // Renumera: la elegida primero y el resto en su orden actual.
                $ids = $servicio->fotos()->pluck('id')->reject(fn ($id) => $id === $foto->id)->values();
                $ids->prepend($foto->id)->each(
                    fn ($id, $posicion) => FotoTrabajo::whereKey($id)->update(['orden' => $posicion])
                );
            }
        });

        return redirect()->back()->with('success', 'Foto actualizada.');
    }

    public function destroy(FotoTrabajo $foto, AlmacenFotos $almacen): RedirectResponse
    {
        Gate::authorize('gestionarFotos', $foto->servicio);

        $ruta = $foto->getAttributes()['ruta'];

        $foto->delete();

        $almacen->borrar($almacen->discoPublico(), $ruta);

        return redirect()->back()->with('success', 'Foto eliminada.');
    }
}
