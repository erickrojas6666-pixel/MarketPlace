<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\FotoTrabajo;
use App\Services\AlmacenFotos;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Los archivos públicos no se borran con la fila (la BD solo elimina en cascada
        // los registros): se limpian aquí. La evidencia de pedidos se conserva a propósito.
        $almacen = app(AlmacenFotos::class);
        $rutas   = FotoTrabajo::query()
            ->whereIn('servicio_id', fn ($q) => $q->select('servicios.id')
                ->from('servicios')
                ->join('perfiles_prestador', 'perfiles_prestador.id', '=', 'servicios.perfil_prestador_id')
                ->where('perfiles_prestador.user_id', $user->id))
            ->get()
            ->map(fn (FotoTrabajo $f) => $f->getAttributes()['ruta'])
            ->all();

        $rutas[] = $user->getAttributes()['foto_path'] ?? null;

        Auth::logout();

        $user->delete();

        $almacen->borrar($almacen->discoPublico(), $rutas);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
