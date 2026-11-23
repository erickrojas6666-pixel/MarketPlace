<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\FotoEvidencia;
use App\Models\FotoTrabajo;
use App\Models\Pedido;
use App\Models\PerfilPrestador;
use App\Models\Servicio;
use App\Models\User;
use App\Services\AlmacenFotos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Fotos: perfil, portafolio (público) y evidencia de pedidos (privada).
 * Usan los discos 'public' y 'local' falsos; no tocan Cloudflare R2.
 */
class FotosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['cliente', 'prestador', 'mediador', 'superadmin'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        Storage::fake('public');
        Storage::fake('local');
    }

    private function foto(string $nombre = 'foto.jpg', int $ancho = 800, int $alto = 600): UploadedFile
    {
        return UploadedFile::fake()->image($nombre, $ancho, $alto);
    }

    /** @return array{0: User, 1: PerfilPrestador, 2: Servicio} */
    private function prestador(string $estado = 'verificado'): array
    {
        $user = User::factory()->create();
        $user->assignRole('prestador');

        $perfil = PerfilPrestador::create(['user_id' => $user->id, 'estado_verificacion' => $estado]);
        $categoria = Categoria::firstOrCreate(['nombre' => 'Plomería']);

        $servicio = Servicio::create([
            'perfil_prestador_id' => $perfil->id,
            'categoria_id'        => $categoria->id,
            'titulo'              => 'Cambio de llave mezcladora',
            'descripcion'         => 'Incluye materiales.',
            'precio_base'         => 450,
            'activo'              => true,
        ]);

        return [$user, $perfil, $servicio];
    }

    private function pedido(Servicio $servicio, User $cliente, string $estado): Pedido
    {
        return Pedido::create([
            'cliente_id'         => $cliente->id,
            'servicio_id'        => $servicio->id,
            'estado'             => $estado,
            'direccion_servicio' => 'Calle 1 #23',
            'fecha_programada'   => now()->addDay(),
        ]);
    }

    private function cliente(): User
    {
        $user = User::factory()->create();
        $user->assignRole('cliente');

        return $user;
    }

    /* ------------------------------ foto de perfil ------------------------------ */

    public function test_usuario_sube_foto_de_perfil_y_se_guarda_en_webp_con_miniatura(): void
    {
        $user = $this->cliente();

        $this->actingAs($user)->post(route('perfil.foto.store'), ['foto' => $this->foto()])
            ->assertSessionHasNoErrors();

        $ruta = $user->fresh()->getAttributes()['foto_path'];

        $this->assertStringStartsWith("perfiles/{$user->id}/", $ruta);
        $this->assertStringEndsWith('.webp', $ruta);
        Storage::disk('public')->assertExists($ruta);
        Storage::disk('public')->assertExists(AlmacenFotos::miniaturaDe($ruta));

        // El contenido guardado es WebP real, no el archivo original.
        $this->assertSame(IMAGETYPE_WEBP, getimagesizefromstring(Storage::disk('public')->get($ruta))[2]);
        $this->assertNotNull($user->fresh()->foto_url);
    }

    public function test_reemplazar_la_foto_borra_la_anterior(): void
    {
        $user = $this->cliente();

        $this->actingAs($user)->post(route('perfil.foto.store'), ['foto' => $this->foto()]);
        $primera = $user->fresh()->getAttributes()['foto_path'];

        $this->actingAs($user)->post(route('perfil.foto.store'), ['foto' => $this->foto('otra.jpg')]);
        $segunda = $user->fresh()->getAttributes()['foto_path'];

        $this->assertNotSame($primera, $segunda);
        Storage::disk('public')->assertMissing($primera);
        Storage::disk('public')->assertExists($segunda);
    }

    public function test_rechaza_archivos_que_no_son_imagen_aunque_tengan_extension_jpg(): void
    {
        $user = $this->cliente();

        $falso = UploadedFile::fake()->createWithContent('truco.jpg', '<?php echo "hola"; ?>');

        $this->actingAs($user)->post(route('perfil.foto.store'), ['foto' => $falso])
            ->assertSessionHasErrors('foto');

        $this->assertNull($user->fresh()->getAttributes()['foto_path']);
    }

    public function test_rechaza_svg(): void
    {
        $user = $this->cliente();

        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->actingAs($user)->post(route('perfil.foto.store'), ['foto' => $svg])
            ->assertSessionHasErrors('foto');
    }

    public function test_invitado_no_puede_subir_foto_de_perfil(): void
    {
        $this->post(route('perfil.foto.store'), ['foto' => $this->foto()])
            ->assertRedirect(route('login'));
    }

    public function test_usuario_elimina_su_foto_de_perfil(): void
    {
        $user = $this->cliente();
        $this->actingAs($user)->post(route('perfil.foto.store'), ['foto' => $this->foto()]);
        $ruta = $user->fresh()->getAttributes()['foto_path'];

        $this->actingAs($user)->delete(route('perfil.foto.destroy'))->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->getAttributes()['foto_path']);
        Storage::disk('public')->assertMissing($ruta);
    }

    /* -------------------------------- portafolio -------------------------------- */

    public function test_prestador_verificado_agrega_foto_al_portafolio(): void
    {
        [$user,, $servicio] = $this->prestador();

        $this->actingAs($user)
            ->post(route('servicios.fotos.store', $servicio), ['foto' => $this->foto(), 'pie' => 'Baño terminado'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $servicio->fotos()->count());
        $this->assertSame('Baño terminado', $servicio->fotos()->first()->pie);
    }

    public function test_prestador_no_puede_subir_fotos_a_servicio_ajeno(): void
    {
        [$duenio,, $servicio] = $this->prestador();
        [$otro] = $this->prestador();

        $this->actingAs($otro)
            ->post(route('servicios.fotos.store', $servicio), ['foto' => $this->foto()])
            ->assertForbidden();

        $this->assertSame(0, $servicio->fotos()->count());
        $this->assertNotNull($duenio);
    }

    public function test_prestador_no_verificado_no_puede_subir_fotos(): void
    {
        [$user,, $servicio] = $this->prestador('pendiente');

        $this->actingAs($user)
            ->post(route('servicios.fotos.store', $servicio), ['foto' => $this->foto()])
            ->assertForbidden();
    }

    public function test_cliente_no_puede_subir_fotos_de_portafolio(): void
    {
        [,, $servicio] = $this->prestador();

        $this->actingAs($this->cliente())
            ->post(route('servicios.fotos.store', $servicio), ['foto' => $this->foto()])
            ->assertForbidden();
    }

    public function test_portafolio_respeta_el_maximo_por_servicio(): void
    {
        config(['marketplace.fotos.max_por_servicio' => 2]);
        [$user,, $servicio] = $this->prestador();

        foreach (range(1, 2) as $i) {
            $this->actingAs($user)->post(route('servicios.fotos.store', $servicio), ['foto' => $this->foto("f{$i}.jpg")]);
        }

        $this->actingAs($user)
            ->post(route('servicios.fotos.store', $servicio), ['foto' => $this->foto('f3.jpg')])
            ->assertSessionHasErrors('foto');

        $this->assertSame(2, $servicio->fotos()->count());
    }

    public function test_marcar_como_portada_reordena_el_portafolio(): void
    {
        [$user,, $servicio] = $this->prestador();

        foreach (range(1, 3) as $i) {
            $this->actingAs($user)->post(route('servicios.fotos.store', $servicio), ['foto' => $this->foto("f{$i}.jpg")]);
        }

        $tercera = $servicio->fotos()->get()->last();

        $this->actingAs($user)->patch(route('fotos-trabajo.update', $tercera), ['portada' => true]);

        $this->assertSame($tercera->id, $servicio->fresh()->fotos()->first()->id);
    }

    public function test_eliminar_foto_de_portafolio_borra_los_archivos(): void
    {
        [$user,, $servicio] = $this->prestador();
        $this->actingAs($user)->post(route('servicios.fotos.store', $servicio), ['foto' => $this->foto()]);

        $foto = $servicio->fotos()->first();
        $ruta = $foto->getAttributes()['ruta'];

        $this->actingAs($user)->delete(route('fotos-trabajo.destroy', $foto))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('fotos_trabajo', ['id' => $foto->id]);
        Storage::disk('public')->assertMissing($ruta);
        Storage::disk('public')->assertMissing(AlmacenFotos::miniaturaDe($ruta));
    }

    public function test_otro_prestador_no_puede_borrar_foto_ajena(): void
    {
        [$duenio,, $servicio] = $this->prestador();
        [$otro] = $this->prestador();
        $this->actingAs($duenio)->post(route('servicios.fotos.store', $servicio), ['foto' => $this->foto()]);

        $this->actingAs($otro)
            ->delete(route('fotos-trabajo.destroy', $servicio->fotos()->first()))
            ->assertForbidden();
    }

    /* --------------------------- perfil público y catálogo --------------------------- */

    public function test_perfil_publico_muestra_el_portafolio_y_no_filtra_rutas_ni_correo(): void
    {
        [$user, $perfil, $servicio] = $this->prestador();

        // 1. Inicia sesión para subir la foto
        $this->actingAs($user)->post(route('servicios.fotos.store', $servicio), ['foto' => $this->foto()]);

        // 2. Cierra sesión para simular a un visitante normal (público)
        auth()->logout();

        // 3. Ahora sí visita el perfil público como cualquier persona
        $respuesta = $this->get(route('prestadores.show', $perfil))->assertOk();

        $this->assertCount(1, $respuesta->viewData('page')['props']['galeria']);

        $json = json_encode($respuesta->viewData('page')['props']);
        $this->assertStringNotContainsString($user->email, $json);
        $this->assertStringNotContainsString('hash_sha256', $json);
    }

    public function test_perfil_publico_de_prestador_no_verificado_da_404(): void
    {
        [, $perfil] = $this->prestador('pendiente');

        $this->get(route('prestadores.show', $perfil))->assertNotFound();
    }

    public function test_catalogo_incluye_las_fotos_del_servicio(): void
    {
        [$user,, $servicio] = $this->prestador();
        $this->actingAs($user)->post(route('servicios.fotos.store', $servicio), ['foto' => $this->foto()]);

        $props = $this->get(route('servicios.index'))->assertOk()->viewData('page')['props'];

        $this->assertCount(1, $props['servicios']['data'][0]['fotos']);
    }

    /* --------------------------- evidencia de pedidos (privada) --------------------------- */

    public function test_prestador_sube_evidencia_en_progreso_y_queda_en_disco_privado_con_auditoria(): void
    {
        [$prestador,, $servicio] = $this->prestador();
        $pedido = $this->pedido($servicio, $this->cliente(), Pedido::EN_PROGRESO);

        $this->actingAs($prestador)
            ->post(route('evidencias.store', $pedido), ['foto' => $this->foto(), 'fase' => 'antes'])
            ->assertCreated();

        $evidencia = FotoEvidencia::first();

        Storage::disk('local')->assertExists($evidencia->getAttributes()['ruta']);
        Storage::disk('public')->assertMissing($evidencia->getAttributes()['ruta']);
        $this->assertDatabaseHas('log_auditoria', ['accion' => 'evidencia.subida', 'entidad_id' => $pedido->id]);
    }

    public function test_no_se_puede_subir_evidencia_si_el_estado_no_lo_permite(): void
    {
        [$prestador,, $servicio] = $this->prestador();
        $pedido = $this->pedido($servicio, $this->cliente(), Pedido::PENDIENTE);

        $this->actingAs($prestador)
            ->post(route('evidencias.store', $pedido), ['foto' => $this->foto(), 'fase' => 'antes'])
            ->assertForbidden();
    }

    public function test_cliente_solo_aporta_evidencia_cuando_el_trabajo_esta_completado(): void
    {
        [,, $servicio] = $this->prestador();
        $cliente = $this->cliente();

        $enCurso = $this->pedido($servicio, $cliente, Pedido::EN_PROGRESO);
        $this->actingAs($cliente)
            ->post(route('evidencias.store', $enCurso), ['foto' => $this->foto(), 'fase' => 'despues'])
            ->assertForbidden();

        $completado = $this->pedido($servicio, $cliente, Pedido::COMPLETADO);
        $this->actingAs($cliente)
            ->post(route('evidencias.store', $completado), ['foto' => $this->foto(), 'fase' => 'despues'])
            ->assertCreated();
    }

    public function test_un_tercero_no_puede_ver_ni_listar_la_evidencia(): void
    {
        [$prestador,, $servicio] = $this->prestador();
        $pedido = $this->pedido($servicio, $this->cliente(), Pedido::EN_PROGRESO);
        $this->actingAs($prestador)->post(route('evidencias.store', $pedido), ['foto' => $this->foto(), 'fase' => 'antes']);

        $intruso = $this->cliente();
        $foto    = FotoEvidencia::first();

        $this->actingAs($intruso)->getJson(route('evidencias.index', $pedido))->assertForbidden();
        $this->actingAs($intruso)->get(route('evidencias.ver', $foto))->assertForbidden();
    }

    public function test_las_partes_ven_la_evidencia_por_redireccion_a_url_firmada_sin_cache(): void
    {
        [$prestador,, $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente, Pedido::EN_PROGRESO);
        $this->actingAs($prestador)->post(route('evidencias.store', $pedido), ['foto' => $this->foto(), 'fase' => 'antes']);

        $foto = FotoEvidencia::first();

        $this->actingAs($cliente)->get(route('evidencias.ver', $foto))
            ->assertRedirect()
            ->assertHeader('Cache-Control');

        $lista = $this->actingAs($cliente)->getJson(route('evidencias.index', $pedido))->assertOk();
        $this->assertCount(1, $lista->json('fotos'));
        $this->assertArrayNotHasKey('ruta', $lista->json('fotos.0'));
    }

    public function test_mediador_solo_ve_evidencia_en_disputa_y_deja_rastro(): void
    {
        [$prestador,, $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente, Pedido::EN_PROGRESO);
        $this->actingAs($prestador)->post(route('evidencias.store', $pedido), ['foto' => $this->foto(), 'fase' => 'antes']);
        $foto = FotoEvidencia::first();

        $mediador = User::factory()->create();
        $mediador->assignRole('mediador');

        $this->actingAs($mediador)->get(route('evidencias.ver', $foto))->assertForbidden();

        $pedido->update(['estado' => Pedido::EN_DISPUTA]);

        $this->actingAs($mediador)->get(route('evidencias.ver', $foto))->assertRedirect();
        $this->assertDatabaseHas('log_auditoria', ['accion' => 'evidencia.consultada', 'entidad_id' => $pedido->id]);
    }

    public function test_solo_el_autor_elimina_su_evidencia_y_no_en_disputa(): void
    {
        [$prestador,, $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente, Pedido::COMPLETADO);
        $this->actingAs($prestador)->post(route('evidencias.store', $pedido), ['foto' => $this->foto(), 'fase' => 'despues']);
        $foto = FotoEvidencia::first();

        $this->actingAs($cliente)->delete(route('evidencias.destroy', $foto))->assertForbidden();

        $pedido->update(['estado' => Pedido::EN_DISPUTA]);
        $this->actingAs($prestador)->delete(route('evidencias.destroy', $foto))->assertForbidden();

        $pedido->update(['estado' => Pedido::COMPLETADO]);
        $this->actingAs($prestador)->delete(route('evidencias.destroy', $foto))->assertNoContent();

        $this->assertSame(0, FotoEvidencia::count());
    }

    public function test_evidencia_respeta_el_maximo_por_persona(): void
    {
        config(['marketplace.fotos.max_evidencias_por_parte' => 1]);
        [$prestador,, $servicio] = $this->prestador();
        $pedido = $this->pedido($servicio, $this->cliente(), Pedido::EN_PROGRESO);

        $this->actingAs($prestador)->post(route('evidencias.store', $pedido), ['foto' => $this->foto(), 'fase' => 'antes'])->assertCreated();

        $this->actingAs($prestador)
            ->postJson(route('evidencias.store', $pedido), ['foto' => $this->foto('b.jpg'), 'fase' => 'durante'])
            ->assertStatus(422);
    }

    public function test_fotos_de_portafolio_son_independientes_de_las_de_evidencia(): void
    {
        [$user,, $servicio] = $this->prestador();
        $this->actingAs($user)->post(route('servicios.fotos.store', $servicio), ['foto' => $this->foto()]);

        $this->assertSame(1, FotoTrabajo::count());
        $this->assertSame(0, FotoEvidencia::count());
    }
}
