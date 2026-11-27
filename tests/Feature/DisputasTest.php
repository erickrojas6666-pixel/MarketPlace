<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Disputa;
use App\Models\FotoEvidencia;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PerfilPrestador;
use App\Models\Servicio;
use App\Models\Transaccione;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * RF-38 — Disputas completas: el cliente abre la disputa desde el pedido, el mediador ve
 * la evidencia de ambas partes y resuelve liberando o devolviendo el pago (cierre del escrow).
 */
class DisputasTest extends TestCase
{
    use RefreshDatabase;

    private const MOTIVO = 'La llave sigue goteando y el prestador dejó el baño mojado.';
    private const NOTAS  = 'Las fotos del después muestran la fuga sin resolver.';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['cliente', 'prestador', 'mediador', 'superadmin'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        Storage::fake('public');
        Storage::fake('local');

        config([
            'marketplace.disputas_habilitadas' => true,
            'marketplace.comision_porcentaje'  => 10,
        ]);
    }

    /* ------------------------------- ayudas ------------------------------- */

    private function cliente(): User
    {
        $user = User::factory()->create();
        $user->assignRole('cliente');

        return $user;
    }

    private function mediador(string $rol = 'mediador'): User
    {
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    /** @return array{0: User, 1: PerfilPrestador, 2: Servicio} */
    private function prestador(): array
    {
        $user = User::factory()->create();
        $user->assignRole('prestador');

        $perfil    = PerfilPrestador::create(['user_id' => $user->id, 'estado_verificacion' => 'verificado']);
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

    private function pedido(Servicio $servicio, User $cliente, string $estado = Pedido::COMPLETADO): Pedido
    {
        return Pedido::create([
            'cliente_id'         => $cliente->id,
            'servicio_id'        => $servicio->id,
            'estado'             => $estado,
            'direccion_servicio' => 'Calle 1 #23',
            'fecha_programada'   => now()->addDay(),
        ]);
    }

    /** Pago cobrado y retenido por la plataforma (lo que deja RetenerPago). */
    private function pagoRetenido(Pedido $pedido, string $metodo = 'simulado', ?string $referencia = null): Pago
    {
        $pago = Pago::forceCreate([
            'pedido_id'      => $pedido->id,
            'monto'          => 450,
            'metodo_pago'    => $metodo,
            'estado'         => 'completado',
            'transaccion_id' => $referencia ?? 'SIM-PRUEBA',
        ]);

        Transaccione::create([
            'pago_id'   => $pago->id,
            'tipo'      => 'cobro_cliente',
            'monto'     => 450,
            'creada_en' => now(),
        ]);

        return $pago;
    }

    private function abrirDisputa(User $cliente, Pedido $pedido): Disputa
    {
        $this->actingAs($cliente)
            ->post(route('pedidos.disputa.store', $pedido), ['motivo' => self::MOTIVO])
            ->assertSessionHasNoErrors();

        return Disputa::where('pedido_id', $pedido->id)->firstOrFail();
    }

    private function resolver(User $mediador, Disputa $disputa, string $decision, string $notas = self::NOTAS)
    {
        return $this->actingAs($mediador)
            ->post(route('mediacion.disputas.resolver', $disputa), ['decision' => $decision, 'notas' => $notas]);
    }

    private function foto(string $nombre = 'foto.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nombre, 800, 600);
    }

    private function props($respuesta): array
    {
        return $respuesta->viewData('page')['props'];
    }

    /* ------------------------------ abrir disputa ------------------------------ */

    public function test_cliente_abre_la_disputa_de_un_pedido_completado(): void
    {
        [$prestador, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);

        $disputa = $this->abrirDisputa($cliente, $pedido);

        $this->assertSame(Pedido::EN_DISPUTA, $pedido->fresh()->estado);
        $this->assertSame(Disputa::ABIERTA, $disputa->estado);
        $this->assertSame($cliente->id, $disputa->abierta_por);
        $this->assertSame(self::MOTIVO, $disputa->motivo);

        $this->assertDatabaseHas('log_auditoria', ['accion' => 'disputa.abierta', 'entidad_id' => $disputa->id]);
        $this->assertDatabaseHas('log_auditoria', ['accion' => 'pedido.estado_cambiado', 'entidad_id' => $pedido->id]);

        // El prestador se entera; quien reclama no necesita aviso de lo que acaba de hacer.
        $this->assertSame(1, $prestador->notifications()->count());
        $this->assertSame(0, $cliente->notifications()->count());
    }

    public function test_no_se_abre_disputa_con_el_modulo_apagado(): void
    {
        config(['marketplace.disputas_habilitadas' => false]);
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);

        $this->actingAs($cliente)
            ->post(route('pedidos.disputa.store', $pedido), ['motivo' => self::MOTIVO])
            ->assertForbidden();

        $this->assertSame(Pedido::COMPLETADO, $pedido->fresh()->estado);
        $this->assertSame(0, Disputa::count());
    }

    public function test_solo_el_cliente_del_pedido_puede_abrirla(): void
    {
        [$prestador, , $servicio] = $this->prestador();
        $pedido = $this->pedido($servicio, $this->cliente());

        $this->actingAs($prestador)
            ->post(route('pedidos.disputa.store', $pedido), ['motivo' => self::MOTIVO])
            ->assertForbidden();

        $this->actingAs($this->cliente())
            ->post(route('pedidos.disputa.store', $pedido), ['motivo' => self::MOTIVO])
            ->assertForbidden();

        $this->assertSame(0, Disputa::count());
    }

    public function test_solo_se_abre_con_el_trabajo_completado(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();

        foreach ([Pedido::EN_PROGRESO, Pedido::CONFIRMADO, Pedido::PENDIENTE] as $estado) {
            $pedido = $this->pedido($servicio, $cliente, $estado);

            $this->actingAs($cliente)
                ->post(route('pedidos.disputa.store', $pedido), ['motivo' => self::MOTIVO])
                ->assertForbidden();
        }

        $this->assertSame(0, Disputa::count());
    }

    public function test_el_motivo_debe_tener_sustancia(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);

        $this->actingAs($cliente)
            ->post(route('pedidos.disputa.store', $pedido), ['motivo' => 'Mal'])
            ->assertSessionHasErrors('motivo');

        $this->assertSame(Pedido::COMPLETADO, $pedido->fresh()->estado);
    }

    public function test_no_se_puede_abrir_dos_veces(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);
        $this->abrirDisputa($cliente, $pedido);

        $this->actingAs($cliente)
            ->post(route('pedidos.disputa.store', $pedido), ['motivo' => self::MOTIVO])
            ->assertForbidden();

        $this->assertSame(1, Disputa::count());
    }

    public function test_el_estado_a_mano_no_abre_ni_cierra_disputas(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);

        // El cliente no puede saltarse el flujo (sin motivo ni registro de la disputa).
        $this->actingAs($cliente)
            ->patch(route('pedidos.updateEstado', $pedido), ['estado' => Pedido::EN_DISPUTA])
            ->assertSessionHas('error');

        $this->assertSame(Pedido::COMPLETADO, $pedido->fresh()->estado);
        $this->assertSame(0, Disputa::count());

        // El mediador tampoco puede cerrar el pedido sin pasar por la resolución (y el dinero).
        $this->abrirDisputa($cliente, $pedido);
        $mediador = $this->mediador();

        $this->actingAs($mediador)
            ->patch(route('pedidos.updateEstado', $pedido), ['estado' => Pedido::CONFIRMADO])
            ->assertSessionHas('error');

        $this->assertSame(Pedido::EN_DISPUTA, $pedido->fresh()->estado);
    }

    public function test_la_api_abre_la_disputa_y_no_ofrece_en_disputa_como_accion_manual(): void
    {
        [$prestador, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);

        Sanctum::actingAs($cliente);
        $this->getJson(route('api.pedidos.show', $pedido))
            ->assertOk()
            ->assertJsonPath('data.acciones_permitidas', [Pedido::CONFIRMADO]);

        $this->postJson(route('api.pedidos.disputa', $pedido), ['motivo' => self::MOTIVO])
            ->assertCreated()
            ->assertJsonPath('data.estado', Disputa::ABIERTA);

        $this->assertSame(Pedido::EN_DISPUTA, $pedido->fresh()->estado);

        // Un tercero ni siquiera sabe que el pedido existe; el prestador sabe pero no puede reclamar.
        $otroPedido = $this->pedido($servicio, $cliente);

        Sanctum::actingAs($this->cliente());
        $this->postJson(route('api.pedidos.disputa', $otroPedido), ['motivo' => self::MOTIVO])->assertNotFound();

        Sanctum::actingAs($prestador);
        $this->postJson(route('api.pedidos.disputa', $otroPedido), ['motivo' => self::MOTIVO])->assertForbidden();
    }

    /* ------------------------- evidencia de ambas partes ------------------------- */

    public function test_el_prestador_puede_aportar_evidencia_durante_la_disputa(): void
    {
        [$prestador, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);
        $this->abrirDisputa($cliente, $pedido);

        $this->actingAs($prestador)
            ->post(route('evidencias.store', $pedido), ['foto' => $this->foto(), 'fase' => 'despues'])
            ->assertCreated();

        $this->assertSame(1, FotoEvidencia::count());
    }

    public function test_el_mediador_ve_la_evidencia_de_ambas_partes(): void
    {
        [$prestador, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);

        $this->actingAs($prestador)->post(route('evidencias.store', $pedido), ['foto' => $this->foto('a.jpg'), 'fase' => 'despues']);
        $this->actingAs($cliente)->post(route('evidencias.store', $pedido), ['foto' => $this->foto('b.jpg'), 'fase' => 'despues']);
        $disputa  = $this->abrirDisputa($cliente, $pedido);
        $mediador = $this->mediador();

        $lista = $this->actingAs($mediador)->getJson(route('evidencias.index', $pedido))->assertOk();

        $this->assertCount(2, $lista->json('fotos'));
        $this->assertEqualsCanonicalizing(['cliente', 'prestador'], array_column($lista->json('fotos'), 'autor'));

        $this->actingAs($mediador)->get(route('evidencias.ver', FotoEvidencia::first()))->assertRedirect();
        $this->assertDatabaseHas('log_auditoria', ['accion' => 'evidencia.consultada', 'entidad_id' => $pedido->id]);

        $props = $this->props($this->actingAs($mediador)->get(route('mediacion.disputas.show', $disputa))->assertOk());
        $this->assertTrue($props['evidenciasVisibles']);
    }

    /* ------------------------------ panel del mediador ------------------------------ */

    public function test_cliente_y_prestador_no_entran_a_la_mediacion(): void
    {
        [$prestador, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $disputa = $this->abrirDisputa($cliente, $this->pedido($servicio, $cliente));

        foreach ([$cliente, $prestador] as $usuario) {
            $this->actingAs($usuario)->get(route('mediacion.disputas.index'))->assertForbidden();
            $this->actingAs($usuario)->get(route('mediacion.disputas.show', $disputa))->assertForbidden();
            $this->actingAs($usuario)->post(route('mediacion.disputas.tomar', $disputa))->assertForbidden();
            $this->resolver($usuario, $disputa, Disputa::FAVOR_CLIENTE)->assertForbidden();
        }

        $this->assertSame(Disputa::ABIERTA, $disputa->fresh()->estado);
        $this->assertSame(Pedido::EN_DISPUTA, $disputa->pedido->fresh()->estado);
    }

    public function test_la_cola_lista_las_pendientes_y_la_ficha_no_filtra_datos_personales(): void
    {
        [$prestador, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $disputa = $this->abrirDisputa($cliente, $this->pedido($servicio, $cliente));
        $this->pagoRetenido($disputa->pedido);
        $mediador = $this->mediador();

        $cola = $this->props($this->actingAs($mediador)->get(route('mediacion.disputas.index'))->assertOk());
        $this->assertCount(1, $cola['disputas']['data']);
        $this->assertSame(1, $cola['pendientes']);

        $respuesta = $this->actingAs($mediador)->get(route('mediacion.disputas.show', $disputa))->assertOk();
        $props     = $this->props($respuesta);

        $this->assertSame('retenido', $props['pago']['estado']);
        $this->assertSame(405.0, $props['pago']['neto']);
        $this->assertSame(45.0, $props['pago']['comision']);
        $this->assertTrue($props['puedeGestionar']);

        // Solo nombres: ni correos ni la dirección del domicilio.
        $json = json_encode($props);
        $this->assertStringNotContainsString($cliente->email, $json);
        $this->assertStringNotContainsString($prestador->email, $json);
        $this->assertStringNotContainsString('Calle 1 #23', $json);
    }

    public function test_el_mediador_no_ve_ni_resuelve_un_caso_del_que_es_parte(): void
    {
        [, , $servicio] = $this->prestador();
        $doble = $this->cliente();
        $doble->assignRole('mediador');

        $disputa = $this->abrirDisputa($doble, $this->pedido($servicio, $doble));

        $cola = $this->props($this->actingAs($doble)->get(route('mediacion.disputas.index'))->assertOk());
        $this->assertCount(0, $cola['disputas']['data']);

        $this->actingAs($doble)->get(route('mediacion.disputas.show', $disputa))->assertForbidden();
        $this->resolver($doble, $disputa, Disputa::FAVOR_CLIENTE)->assertForbidden();

        $this->assertSame(Pedido::EN_DISPUTA, $disputa->pedido->fresh()->estado);
    }

    /* ------------------------------ resolver: a favor del prestador ------------------------------ */

    public function test_a_favor_del_prestador_confirma_el_pedido_y_libera_el_pago(): void
    {
        [$prestador, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);
        $pago    = $this->pagoRetenido($pedido);
        $disputa = $this->abrirDisputa($cliente, $pedido);
        $mediador = $this->mediador();

        $this->resolver($mediador, $disputa, Disputa::FAVOR_PRESTADOR)->assertSessionHasNoErrors();

        $disputa->refresh();
        $this->assertSame(Disputa::RESUELTA_FAVOR_PRESTADOR, $disputa->estado);
        $this->assertSame($mediador->id, $disputa->mediador_id);
        $this->assertSame(self::NOTAS, $disputa->resolucion_notas);
        $this->assertSame(Pedido::CONFIRMADO, $pedido->fresh()->estado);

        // 450 → el prestador recibe 405 y la plataforma se queda con 45 (10 %).
        $this->assertDatabaseHas('transacciones', ['pago_id' => $pago->id, 'tipo' => 'liberacion_prestador', 'monto' => 405]);
        $this->assertDatabaseHas('transacciones', ['pago_id' => $pago->id, 'tipo' => 'comision_plataforma', 'monto' => 45]);
        $this->assertDatabaseMissing('transacciones', ['pago_id' => $pago->id, 'tipo' => 'reembolso_cliente']);
        $this->assertSame('completado', $pago->fresh()->estado);

        $this->assertDatabaseHas('log_auditoria', ['accion' => 'disputa.resuelta', 'entidad_id' => $disputa->id]);
        $this->assertDatabaseHas('log_auditoria', ['accion' => 'pago.liberado', 'entidad_id' => $pago->id]);

        // Las dos partes reciben aviso de la decisión.
        $this->assertSame(2, $prestador->notifications()->count());
        $this->assertSame(1, $cliente->notifications()->count());

        // Cerrado el caso, el mediador ya no puede abrir las fotos privadas.
        $this->actingAs($mediador)->getJson(route('evidencias.index', $pedido))->assertForbidden();
    }

    public function test_las_partes_ven_la_decision_en_su_dashboard(): void
    {
        [$prestador, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);
        $disputa = $this->abrirDisputa($cliente, $pedido);
        $this->resolver($this->mediador(), $disputa, Disputa::FAVOR_CLIENTE);

        $delCliente = $this->props($this->actingAs($cliente)->get(route('dashboard'))->assertOk());
        $this->assertSame(Disputa::RESUELTA_FAVOR_CLIENTE, $delCliente['misPedidos'][0]['disputa']['estado']);
        $this->assertSame(self::NOTAS, $delCliente['misPedidos'][0]['disputa']['resolucion_notas']);

        $delPrestador = $this->props($this->actingAs($prestador)->get(route('dashboard'))->assertOk());
        $this->assertSame(Disputa::RESUELTA_FAVOR_CLIENTE, $delPrestador['pedidos'][0]['disputa']['estado']);
    }

    /* ------------------------------ resolver: a favor del cliente ------------------------------ */

    public function test_a_favor_del_cliente_cancela_el_pedido_y_reembolsa_el_pago(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);
        $pago    = $this->pagoRetenido($pedido);
        $disputa = $this->abrirDisputa($cliente, $pedido);

        $this->resolver($this->mediador(), $disputa, Disputa::FAVOR_CLIENTE)->assertSessionHasNoErrors();

        $this->assertSame(Disputa::RESUELTA_FAVOR_CLIENTE, $disputa->fresh()->estado);
        $this->assertSame(Pedido::CANCELADO, $pedido->fresh()->estado);
        $this->assertSame('reembolsado', $pago->fresh()->estado);

        // Reembolso completo y ninguna comisión sobre un trabajo que no se concretó.
        $this->assertDatabaseHas('transacciones', ['pago_id' => $pago->id, 'tipo' => 'reembolso_cliente', 'monto' => 450]);
        $this->assertDatabaseMissing('transacciones', ['pago_id' => $pago->id, 'tipo' => 'liberacion_prestador']);
        $this->assertDatabaseMissing('transacciones', ['pago_id' => $pago->id, 'tipo' => 'comision_plataforma']);
        $this->assertDatabaseHas('log_auditoria', ['accion' => 'pago.reembolsado', 'entidad_id' => $pago->id]);
    }

    public function test_sin_pago_cobrado_solo_se_cierra_el_pedido(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);
        $disputa = $this->abrirDisputa($cliente, $pedido);

        $this->resolver($this->mediador(), $disputa, Disputa::FAVOR_CLIENTE)->assertSessionHasNoErrors();

        $this->assertSame(Pedido::CANCELADO, $pedido->fresh()->estado);
        $this->assertSame(0, Transaccione::count());
    }

    public function test_con_mercado_pago_se_pide_la_devolucion_real_con_clave_de_idempotencia(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-TOKEN']);
        Http::fake(['api.mercadopago.com/*' => Http::response(['id' => 1], 201)]);

        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);
        $pago    = $this->pagoRetenido($pedido, 'mercadopago', 'MP-123456');
        $disputa = $this->abrirDisputa($cliente, $pedido);

        $this->resolver($this->mediador(), $disputa, Disputa::FAVOR_CLIENTE)->assertSessionHasNoErrors();

        Http::assertSent(fn ($peticion) => str_contains($peticion->url(), '/v1/payments/123456/refunds')
            && $peticion->hasHeader('X-Idempotency-Key', "disputa-{$disputa->id}-pago-{$pago->id}"));

        $this->assertSame('reembolsado', $pago->fresh()->estado);
        $this->assertSame(Pedido::CANCELADO, $pedido->fresh()->estado);
    }

    public function test_si_mercado_pago_falla_no_queda_nada_a_medias(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-TOKEN']);
        Http::fake(['api.mercadopago.com/*' => Http::response(['message' => 'error'], 500)]);

        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);
        $pago    = $this->pagoRetenido($pedido, 'mercadopago', 'MP-123456');
        $disputa = $this->abrirDisputa($cliente, $pedido);

        $this->resolver($this->mediador(), $disputa, Disputa::FAVOR_CLIENTE)->assertSessionHas('error');

        // Ni el pedido, ni la disputa, ni el libro cambiaron: se puede reintentar.
        $this->assertSame(Pedido::EN_DISPUTA, $pedido->fresh()->estado);
        $this->assertSame(Disputa::ABIERTA, $disputa->fresh()->estado);
        $this->assertSame('completado', $pago->fresh()->estado);
        $this->assertDatabaseMissing('transacciones', ['pago_id' => $pago->id, 'tipo' => 'reembolso_cliente']);
    }

    /* ------------------------------ reglas de la resolución ------------------------------ */

    public function test_una_disputa_resuelta_no_mueve_el_dinero_dos_veces(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente  = $this->cliente();
        $pedido   = $this->pedido($servicio, $cliente);
        $pago     = $this->pagoRetenido($pedido);
        $disputa  = $this->abrirDisputa($cliente, $pedido);
        $mediador = $this->mediador();

        $this->resolver($mediador, $disputa, Disputa::FAVOR_PRESTADOR)->assertSessionHasNoErrors();

        // Segundo intento (doble clic o decisión contraria): rechazado y sin efecto.
        $this->resolver($mediador, $disputa, Disputa::FAVOR_CLIENTE)->assertSessionHas('error');
        $this->resolver($mediador, $disputa, Disputa::FAVOR_PRESTADOR)->assertSessionHas('error');

        $this->assertSame(Disputa::RESUELTA_FAVOR_PRESTADOR, $disputa->fresh()->estado);
        $this->assertSame(Pedido::CONFIRMADO, $pedido->fresh()->estado);
        $this->assertSame(1, Transaccione::where('pago_id', $pago->id)->where('tipo', 'liberacion_prestador')->count());
        $this->assertSame(0, Transaccione::where('pago_id', $pago->id)->where('tipo', 'reembolso_cliente')->count());
    }

    public function test_la_decision_y_las_notas_son_obligatorias(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente  = $this->cliente();
        $disputa  = $this->abrirDisputa($cliente, $this->pedido($servicio, $cliente));
        $mediador = $this->mediador();

        $this->resolver($mediador, $disputa, 'nadie')->assertSessionHasErrors('decision');
        $this->resolver($mediador, $disputa, Disputa::FAVOR_CLIENTE, 'corta')->assertSessionHasErrors('notas');

        $this->assertSame(Pedido::EN_DISPUTA, $disputa->pedido->fresh()->estado);
        $this->assertSame(Disputa::ABIERTA, $disputa->fresh()->estado);
    }

    public function test_un_caso_tomado_por_otro_mediador_solo_lo_resuelve_el_o_un_superadmin(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);
        $disputa = $this->abrirDisputa($cliente, $pedido);

        $primero  = $this->mediador();
        $segundo  = $this->mediador();
        $superadmin = $this->mediador('superadmin');

        $this->actingAs($primero)->post(route('mediacion.disputas.tomar', $disputa))->assertSessionHasNoErrors();
        $this->assertSame(Disputa::EN_REVISION, $disputa->fresh()->estado);
        $this->assertSame($primero->id, $disputa->fresh()->mediador_id);
        $this->assertDatabaseHas('log_auditoria', ['accion' => 'disputa.tomada', 'entidad_id' => $disputa->id]);

        // Tomarla de nuevo (él mismo) es inofensivo; otro mediador no puede ni tomarla ni resolverla.
        $this->actingAs($primero)->post(route('mediacion.disputas.tomar', $disputa))->assertSessionHasNoErrors();
        $this->actingAs($segundo)->post(route('mediacion.disputas.tomar', $disputa))->assertSessionHas('error');
        $this->resolver($segundo, $disputa, Disputa::FAVOR_CLIENTE)->assertSessionHas('error');

        $this->assertSame(Pedido::EN_DISPUTA, $pedido->fresh()->estado);
        $this->assertSame($primero->id, $disputa->fresh()->mediador_id);

        $this->resolver($superadmin, $disputa, Disputa::FAVOR_CLIENTE)->assertSessionHasNoErrors();
        $this->assertSame(Pedido::CANCELADO, $pedido->fresh()->estado);
    }

    public function test_el_mediador_que_solo_tiene_ese_rol_va_directo_a_la_cola(): void
    {
        $this->actingAs($this->mediador())
            ->get(route('dashboard'))
            ->assertRedirect(route('mediacion.disputas.index'));
    }

    /* ------------------- regresión: el endpoint de liberación de siempre ------------------- */

    public function test_el_cliente_sigue_liberando_el_pago_de_un_pedido_confirmado_por_la_api(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente, Pedido::CONFIRMADO);
        $pago    = $this->pagoRetenido($pedido);

        Sanctum::actingAs($cliente);

        $this->postJson(route('api.pagos.liberar', $pago))
            ->assertOk()
            ->assertJsonPath('data.estado', 'liberado');

        $this->assertDatabaseHas('transacciones', ['pago_id' => $pago->id, 'tipo' => 'liberacion_prestador', 'monto' => 405]);

        // Y no se puede liberar dos veces.
        $this->postJson(route('api.pagos.liberar', $pago))->assertStatus(422);
    }
}
