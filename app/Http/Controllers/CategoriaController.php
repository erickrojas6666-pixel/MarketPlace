<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoriaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:categorias,nombre',
            'descripcion' => 'nullable|string|max:255',
            'icono' => 'nullable|string|max:50',
        ]);

        Categoria::create([
            'nombre' => $request->nombre,
            'slug' => Str::slug($request->nombre),
            'descripcion' => $request->descripcion,
            'icono' => $request->icono,
        ]);

        return redirect()->back()->with('success', 'Categoría creada exitosamente.');
    }

    public function update(Request $request, Categoria $categoria): RedirectResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:categorias,nombre,' . $categoria->id,
            'descripcion' => 'nullable|string|max:255',
            'icono' => 'nullable|string|max:50',
        ]);

        $categoria->update([
            'nombre' => $request->nombre,
            'slug' => Str::slug($request->nombre),
            'descripcion' => $request->descripcion,
            'icono' => $request->icono,
        ]);

        return redirect()->back()->with('success', 'Categoría actualizada exitosamente.');
    }

    public function destroy(Categoria $categoria): RedirectResponse
    {
        // Opcional: verificar si tiene servicios vinculados antes de eliminar
        if ($categoria->servicios()->count() > 0) {
            return redirect()->back()->with('error', 'No se puede eliminar la categoría porque tiene servicios vinculados.');
        }

        $categoria->delete();

        return redirect()->back()->with('success', 'Categoría eliminada con éxito.');
    }
}