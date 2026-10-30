<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoriaController extends Controller
{
    // Listado de categorías (útil para el panel de administración)
    public function index(): Response
    {
        return Inertia::render('Admin/Categorias/Index', [
            'categorias' => Categoria::withCount('servicios')->get(),
        ]);
    }

    // Guardar una nueva categoría
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:categorias,nombre',
            'descripcion' => 'nullable|string',
            'icono' => 'nullable|string|max:100',
        ]);

        Categoria::create([
            'nombre' => $request->nombre,
            'slug' => Str::slug($request->nombre),
            'descripcion' => $request->descripcion,
            'icono' => $request->icono,
        ]);

        return redirect()->back()->with('success', 'Categoría creada con éxito.');
    }

    // Actualizar una categoría existente
    public function update(Request $request, Categoria $categoria): RedirectResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:categorias,nombre,' . $categoria->id,
            'descripcion' => 'nullable|string',
            'icono' => 'nullable|string|max:100',
        ]);

        $categoria->update([
            'nombre' => $request->nombre,
            'slug' => Str::slug($request->nombre),
            'descripcion' => $request->descripcion,
            'icono' => $request->icono,
        ]);

        return redirect()->back()->with('success', 'Categoría actualizada con éxito.');
    }

    // Eliminar una categoría
    public function destroy(Categoria $categoria): RedirectResponse
    {
        $categoria->delete();

        return redirect()->back()->with('success', 'Categoría eliminada con éxito.');
    }
}