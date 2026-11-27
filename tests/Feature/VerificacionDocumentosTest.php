<?php

namespace Tests\Feature;

use App\Models\DocumentosVerificacion;
use App\Models\PerfilPrestador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Verificación de identidad (RF-05 a RF-08): INE y comprobante cifrados en disco privado,
 * revisión con motivo obligatorio y auditoría de cada consulta. Usan un disco falso.
 */
class VerificacionDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private const TIPOS = ['ine_frente', 'ine_reverso', 'comprobante_domicilio'];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['cliente', 'prestador', 'admin', 'superadmin', 'verificador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        Storage::fake('local');
    }

    private function prestador(): array
    {
        $user = User::factory()->create();
        $user->assignRole('prestador');
        $perfil = PerfilPrestador::create(['user_id' => $user->id, 'estado_verificacion' => 'pendiente']);

        return [$user, $perfil];
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->image('ine.jpg', 900, 600);
    }

    private function subir(User $user, string $tipo)
    {
        return $this->actingAs($user)->post(route('prestador.verificacion.documentos.store'), [
            'tipo' => $tipo,
            'foto' => $this->foto(),
        ]);
    }

    private function subirTodos(User $user): void
    {
        foreach (self::TIPOS as $tipo) {
            $this->subir($user, $tipo)->assertSessionHasNoErrors();
        }
    }

    /* ------------------------------ lado del prestador ------------------------------ */

    public function test_el_documento_se_guarda_cifrado_y_no_como_imagen_legible(): void
    {
        [$user, $perfil] = $this->prestador();

        $this->subir($user, 'ine_frente')->assertSessionHasNoErrors();

        $doc = DocumentosVerificacion::firstOrFail();
        $contenido = Storage::disk('local')->get($doc->ruta_cifrada);

        $this->assertSame('pendiente', $doc->estado);
        $this->assertStringStartsWith("verificacion/{$perfil->id}/", $doc->ruta_cifrada);
        $this->assertStringEndsWith('.enc', $doc->ruta_cifrada);
        // Un WebP legible empezaría por "RIFF": el archivo guardado debe ser ilegible.
        $this->assertStringNotContainsString('RIFF', substr($contenido, 0, 16));
        $this->assertStringNotContainsString('WEBP', $contenido);
    }

    public function test_rechaza_archivos_que_no_son_imagen(): void
    {
        [$user] = $this->prestador();

        $falso = UploadedFile::fake()->createWithContent('ine.jpg', '<?php echo 1; ?>');

        $this->actingAs($user)->post(route('prestador.verificacion.documentos.store'), [
            'tipo' => 'ine_frente',
            'foto' => $falso,
        ])->assertSessionHasErrors('foto');

        $this->assertSame(0, DocumentosVerificacion::count());
    }

    public function test_tipo_de_documento_no_permitido_se_rechaza(): void
    {
        [$user] = $this->prestador();

        $this->actingAs($user)->post(route('prestador.verificacion.documentos.store'), [
            'tipo' => 'antecedentes',
            'foto' => $this->foto(),
        ])->assertSessionHasErrors('tipo');
    }

    public function test_volver_a_subir_reemplaza_el_documento_y_borra_el_archivo_anterior(): void
    {
        [$user] = $this->prestador();

        $this->subir($user, 'ine_frente');
        $primera = DocumentosVerificacion::firstOrFail()->ruta_cifrada;

        $this->subir($user, 'ine_frente');

        $this->assertSame(1, DocumentosVerificacion::count());
        Storage::disk('local')->assertMissing($primera);
        Storage::disk('local')->assertExists(DocumentosVerificacion::firstOrFail()->ruta_cifrada);
    }

    public function test_el_prestador_solo_ve_sus_propios_documentos(): void
    {
        [$duenio] = $this->prestador();
        [$otro] = $this->prestador();
        $this->subir($duenio, 'ine_frente');
        $doc = DocumentosVerificacion::firstOrFail();

        $this->actingAs($otro)->get(route('prestador.verificacion.documentos.ver', $doc))->assertForbidden();

        $respuesta = $this->actingAs($duenio)->get(route('prestador.verificacion.documentos.ver', $doc))->assertOk();
        $this->assertSame('image/webp', $respuesta->headers->get('Content-Type'));
        $this->assertStringStartsWith('RIFF', $respuesta->getContent());
    }

    public function test_un_cliente_no_accede_al_modulo_de_verificacion(): void
    {
        $cliente = User::factory()->create();
        $cliente->assignRole('cliente');

        $this->actingAs($cliente)->get(route('prestador.verificacion.show'))->assertForbidden();
    }

    public function test_no_se_puede_enviar_sin_los_tres_documentos_ni_sin_aceptar_el_aviso(): void
    {
        [$user] = $this->prestador();
        $this->subir($user, 'ine_frente');

        $this->actingAs($user)->post(route('prestador.verificacion.enviar'), ['acepta_aviso' => true])
            ->assertSessionHasErrors('acepta_aviso');

        $this->subirTodos($user);

        $this->actingAs($user)->post(route('prestador.verificacion.enviar'), [])
            ->assertSessionHasErrors('acepta_aviso');
    }

    public function test_enviar_pasa_a_en_revision_y_bloquea_cambios_de_documentos(): void
    {
        [$user, $perfil] = $this->prestador();
        $this->subirTodos($user);

        $this->actingAs($user)->post(route('prestador.verificacion.enviar'), ['acepta_aviso' => true])
            ->assertSessionHasNoErrors();

        $perfil->refresh();
        $this->assertSame('en_revision', $perfil->estadoVisible());
        $this->assertDatabaseHas('log_auditoria', ['accion' => 'prestador.verificacion_enviada', 'entidad_id' => $perfil->id]);

        $this->subir($user, 'ine_frente')->assertSessionHasErrors('foto');
    }

    /* ------------------------------ lado del revisor ------------------------------ */

    public function test_sin_motivo_el_revisor_no_puede_ver_documentos(): void
    {
        [$user] = $this->prestador();
        $this->subir($user, 'ine_frente');
        $doc = DocumentosVerificacion::firstOrFail();

        $this->actingAs($this->admin())->get(route('admin.documentos.ver', $doc))->assertForbidden();
    }

    public function test_declarar_el_motivo_abre_el_acceso_y_queda_en_auditoria(): void
    {
        [$user, $perfil] = $this->prestador();
        $this->subir($user, 'ine_frente');

        $this->actingAs($this->admin())
            ->post(route('admin.documentos.acceso', $perfil), ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->post(route('admin.documentos.acceso', $perfil), ['motivo' => 'Verificación inicial de identidad'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('log_auditoria', ['accion' => 'documentos.acceso_autorizado', 'entidad_id' => $perfil->id]);
    }

    public function test_con_acceso_vigente_se_ve_el_documento_y_cada_lectura_se_audita(): void
    {
        [$user, $perfil] = $this->prestador();
        $this->subir($user, 'ine_frente');
        $doc = DocumentosVerificacion::firstOrFail();

        $respuesta = $this->actingAs($this->admin())
            ->withSession(["acceso_documentos.{$perfil->id}" => ['motivo' => 'Revisión inicial', 'hasta' => now()->addMinutes(10)->timestamp]])
            ->get(route('admin.documentos.ver', $doc))
            ->assertOk();

        $this->assertSame('image/webp', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $this->assertStringStartsWith('RIFF', $respuesta->getContent());
        $this->assertDatabaseHas('log_auditoria', ['accion' => 'documento.consultado', 'entidad_id' => $perfil->id]);
    }

    public function test_un_acceso_vencido_no_sirve(): void
    {
        [$user, $perfil] = $this->prestador();
        $this->subir($user, 'ine_frente');
        $doc = DocumentosVerificacion::firstOrFail();

        $this->actingAs($this->admin())
            ->withSession(["acceso_documentos.{$perfil->id}" => ['motivo' => 'x', 'hasta' => now()->subMinute()->timestamp]])
            ->get(route('admin.documentos.ver', $doc))
            ->assertForbidden();
    }

    public function test_un_archivo_alterado_en_el_almacenamiento_no_se_sirve(): void
    {
        [$user, $perfil] = $this->prestador();
        $this->subir($user, 'ine_frente');
        $doc = DocumentosVerificacion::firstOrFail();

        Storage::disk('local')->put($doc->ruta_cifrada, 'contenido-manipulado');

        $this->actingAs($this->admin())
            ->withSession(["acceso_documentos.{$perfil->id}" => ['motivo' => 'x', 'hasta' => now()->addMinutes(5)->timestamp]])
            ->get(route('admin.documentos.ver', $doc))
            ->assertNotFound();
    }

    public function test_un_prestador_no_puede_usar_las_rutas_de_revision(): void
    {
        [$user, $perfil] = $this->prestador();
        $this->subir($user, 'ine_frente');
        $doc = DocumentosVerificacion::firstOrFail();

        $this->actingAs($user)->get(route('admin.documentos.ver', $doc))->assertForbidden();
        $this->actingAs($user)->post(route('admin.documentos.acceso', $perfil), ['motivo' => 'curioseando'])->assertForbidden();
        $this->actingAs($user)->patch(route('admin.documentos.revisar', $doc), ['estado' => 'valido'])->assertForbidden();
    }

    public function test_rechazar_un_documento_exige_motivo(): void
    {
        [$user] = $this->prestador();
        $this->subir($user, 'ine_frente');
        $doc = DocumentosVerificacion::firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.documentos.revisar', $doc), ['estado' => 'rechazado'])
            ->assertSessionHasErrors('motivo');

        $this->patch(route('admin.documentos.revisar', $doc), ['estado' => 'rechazado', 'motivo' => 'Foto borrosa'])
            ->assertSessionHasNoErrors();

        $this->assertSame('rechazado', $doc->fresh()->estado);
        $this->assertSame('Foto borrosa', $doc->fresh()->motivo);
    }

    /* ------------------------------ aprobación / rechazo ------------------------------ */

    public function test_no_se_aprueba_a_un_prestador_sin_documentos(): void
    {
        [, $perfil] = $this->prestador();

        $this->actingAs($this->admin())
            ->patch(route('admin.prestadores.updateStatus', $perfil), ['estado' => 'aprobado'])
            ->assertSessionHas('error');

        $this->assertSame('pendiente', $perfil->fresh()->estado_verificacion);
    }

    public function test_aprobar_con_documentos_completos_marca_los_documentos_como_validos(): void
    {
        [$user, $perfil] = $this->prestador();
        $this->subirTodos($user);

        $this->actingAs($this->admin())
            ->patch(route('admin.prestadores.updateStatus', $perfil), ['estado' => 'aprobado'])
            ->assertSessionHasNoErrors();

        $this->assertSame('verificado', $perfil->fresh()->estado_verificacion);
        $this->assertSame(3, $perfil->documentos()->where('estado', 'valido')->count());
    }

    public function test_no_se_aprueba_con_un_documento_rechazado(): void
    {
        [$user, $perfil] = $this->prestador();
        $this->subirTodos($user);
        $perfil->documentos()->where('tipo', 'ine_frente')->update(['estado' => 'rechazado', 'motivo' => 'Borrosa']);

        $this->actingAs($this->admin())
            ->patch(route('admin.prestadores.updateStatus', $perfil), ['estado' => 'aprobado'])
            ->assertSessionHas('error');

        $this->assertSame('pendiente', $perfil->fresh()->estado_verificacion);
    }

    public function test_rechazar_al_prestador_exige_motivo_y_lo_guarda(): void
    {
        [, $perfil] = $this->prestador();
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.prestadores.updateStatus', $perfil), ['estado' => 'rechazado'])
            ->assertSessionHasErrors('motivo');

        $this->patch(route('admin.prestadores.updateStatus', $perfil), [
            'estado' => 'rechazado',
            'motivo' => 'La INE no coincide con el nombre del perfil.',
        ])->assertSessionHasNoErrors();

        $perfil->refresh();
        $this->assertSame('rechazado', $perfil->estado_verificacion);
        $this->assertSame('La INE no coincide con el nombre del perfil.', $perfil->motivo_rechazo);
        $this->assertTrue($perfil->puedeEditarDocumentos());
    }

    /* ---------------------------------- retención ---------------------------------- */

    public function test_la_purga_elimina_el_archivo_de_documentos_resueltos_vencidos(): void
    {
        [$user, $perfil] = $this->prestador();
        $this->subir($user, 'ine_frente');
        $doc = DocumentosVerificacion::firstOrFail();
        $ruta = $doc->ruta_cifrada;

        $perfil->update(['estado_verificacion' => 'verificado']);
        DocumentosVerificacion::whereKey($doc->id)->update(['estado' => 'valido', 'updated_at' => now()->subDays(400)]);

        $this->artisan('verificacion:purgar-documentos')->assertSuccessful();

        Storage::disk('local')->assertMissing($ruta);
        $doc->refresh();
        $this->assertNotNull($doc->purgado_at);
        $this->assertSame('', $doc->ruta_cifrada);
        $this->assertDatabaseHas('log_auditoria', ['accion' => 'documento.purgado', 'entidad_id' => $perfil->id]);
    }

    public function test_la_purga_respeta_los_documentos_recientes_o_de_solicitudes_abiertas(): void
    {
        [$user, $perfil] = $this->prestador();
        $this->subir($user, 'ine_frente');
        $doc = DocumentosVerificacion::firstOrFail();

        // Perfil aún pendiente: aunque el documento sea viejo, no se toca.
        DocumentosVerificacion::whereKey($doc->id)->update(['estado' => 'valido', 'updated_at' => now()->subDays(400)]);

        $this->artisan('verificacion:purgar-documentos')->assertSuccessful();

        Storage::disk('local')->assertExists($doc->fresh()->ruta_cifrada);
        $this->assertNull($doc->fresh()->purgado_at);
    }
}
