<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoriaController extends Controller
{
    /**
     * Muestra el listado de categorías.
     */
    public function index(): Response
    {
        $categorias = Categoria::withCount('servicios')
            ->orderBy('nombre', 'asc')
            ->get();

        return Inertia::render('Admin/Categorias/Index', [
            'categorias' => $categorias,
        ]);
    }

    /**
     * Guarda una nueva categoría.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre'      => 'required|string|max:100|unique:categorias,nombre',
            'descripcion' => 'nullable|string|max:255',
        ]);

        Categoria::create($validated);

        return redirect()->back()->with('success', 'Categoría creada exitosamente.');
    }

    /**
     * Actualiza una categoría existente.
     */
    public function update(Request $request, Categoria $categoria): RedirectResponse
    {
        $validated = $request->validate([
            'nombre'      => 'required|string|max:100|unique:categorias,nombre,' . $categoria->id,
            'descripcion' => 'nullable|string|max:255',
        ]);

        $categoria->update($validated);

        return redirect()->back()->with('success', 'Categoría actualizada exitosamente.');
    }

    /**
     * Elimina una categoría si no tiene servicios asociados.
     */
    public function destroy(Categoria $categoria): RedirectResponse
    {
        if (method_exists($categoria, 'servicios') && $categoria->servicios()->exists()) {
            return redirect()->back()->with('error', 'No se puede eliminar la categoría porque tiene servicios asociados.');
        }

        $categoria->delete();

        return redirect()->back()->with('success', 'Categoría eliminada con éxito.');
    }
}