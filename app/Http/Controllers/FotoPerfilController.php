<?php

namespace App\Http\Controllers;

use App\Services\AlmacenFotos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Foto de perfil del usuario (cliente o prestador). RF-03. */
class FotoPerfilController extends Controller
{
    public function store(Request $request, AlmacenFotos $almacen): RedirectResponse
    {
        $request->validate([
            'foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('marketplace.fotos.max_kb')],
        ]);

        $user = $request->user();

        $nueva = $almacen->guardar(
            $request->file('foto'),
            $almacen->discoPublico(),
            "perfiles/{$user->id}",
        );

        $anterior = $user->getAttributes()['foto_path'] ?? null;

        $user->forceFill(['foto_path' => $nueva['ruta']])->save();

        // Se borra la anterior después de guardar la nueva: nunca se queda sin foto por un fallo.
        $almacen->borrar($almacen->discoPublico(), $anterior);

        return redirect()->back()->with('success', 'Foto de perfil actualizada.');
    }

    public function destroy(Request $request, AlmacenFotos $almacen): RedirectResponse
    {
        $user     = $request->user();
        $anterior = $user->getAttributes()['foto_path'] ?? null;

        $user->forceFill(['foto_path' => null])->save();

        $almacen->borrar($almacen->discoPublico(), $anterior);

        return redirect()->back()->with('success', 'Foto de perfil eliminada.');
    }
}
