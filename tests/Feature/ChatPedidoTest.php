<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Mensaje;
use App\Models\Pedido;
use App\Models\PerfilPrestador;
use App\Models\Servicio;
use App\Models\User;
use App\Notifications\NuevoMensajeChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * RF-22 — Chat del pedido: solo cliente y prestador, solo texto, tiempo real por Web Push,
 * envío idempotente (cola offline) y retención de datos.
 */
class ChatPedidoTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'a1b2c3d4-e5f6-4a7b-8c9d-0123456789ab';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['cliente', 'prestador', 'mediador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        config([
            'marketplace.chat.max_caracteres' => 200,
            'marketplace.chat.retencion_dias' => 180,
        ]);
    }

    /* ------------------------------- ayudas ------------------------------- */

    private function cliente(): User
    {
        $user = User::factory()->create();
        $user->assignRole('cliente');

        return $user;
    }

    private function mediador(): User
    {
        $user = User::factory()->create();
        $user->assignRole('mediador');

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

    private function pedido(Servicio $servicio, User $cliente, string $estado = Pedido::EN_PROGRESO): Pedido
    {
        return Pedido::create([
            'cliente_id'         => $cliente->id,
            'servicio_id'        => $servicio->id,
            'estado'             => $estado,
            'direccion_servicio' => 'Calle 1 #23',
            'fecha_programada'   => now()->addDay(),
        ]);
    }

    private function enviar(User $quien, Pedido $pedido, string $texto, ?string $clave = null)
    {
        return $this->withHeaders($clave ? ['Idempotency-Key' => $clave] : [])
            ->actingAs($quien)
            ->postJson(route('mensajes.store', $pedido), ['contenido' => $texto]);
    }

    private function leer(User $quien, Pedido $pedido, array $consulta = [])
    {
        return $this->actingAs($quien)->getJson(route('mensajes.index', ['pedido' => $pedido->id] + $consulta));
    }

    /* ------------------------------- conversación ------------------------------- */

    public function test_cliente_y_prestador_conversan_y_el_sondeo_trae_solo_lo_nuevo(): void
    {
        [$prestador, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);

        $primero = $this->enviar($cliente, $pedido, 'Hola, ¿a qué hora llegas?')
            ->assertCreated()
            ->assertJsonPath('mensaje.mio', true)
            ->assertJsonPath('mensaje.contenido', 'Hola, ¿a qué hora llegas?')
            ->assertJsonPath('duplicado', false);

        $this->enviar($prestador, $pedido, 'En una hora estoy ahí.')
            ->assertCreated()
            ->assertJsonPath('mensaje.mio', true);

        // Primera carga del cliente: los dos mensajes, en orden, y solo el primero es suyo.
        $carga = $this->leer($cliente, $pedido)->assertOk();
        $this->assertSame(['Hola, ¿a qué hora llegas?', 'En una hora estoy ahí.'], array_column($carga->json('mensajes'), 'contenido'));
        $this->assertSame([true, false], array_column($carga->json('mensajes'), 'mio'));
        $carga->assertJsonPath('puede_escribir', true)->assertJsonPath('maximo', 200);

        // Al avisarle el push, el cliente pide solo lo posterior al último que ya tiene.
        $sondeo = $this->leer($cliente, $pedido, ['despues' => $primero->json('mensaje.id')])->assertOk();
        $this->assertCount(1, $sondeo->json('mensajes'));
        $this->assertSame('En una hora estoy ahí.', $sondeo->json('mensajes.0.contenido'));
    }

    public function test_la_respuesta_no_expone_ids_de_usuario_ni_datos_personales(): void
    {
        [$prestador, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);

        $this->enviar($cliente, $pedido, 'Hola');

        $cuerpo = $this->leer($prestador, $pedido)->assertOk()->getContent();

        $this->assertStringNotContainsString($cliente->email, $cuerpo);
        $this->assertStringNotContainsString('emisor_id', $cuerpo);
        $this->assertStringNotContainsString('Calle 1 #23', $cuerpo);
    }

    public function test_un_tercero_no_sabe_que_el_chat_existe(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);
        $this->enviar($cliente, $pedido, 'Privado');

        foreach ([$this->cliente(), $this->mediador()] as $ajeno) {
            $this->leer($ajeno, $pedido)->assertNotFound();
            $this->enviar($ajeno, $pedido, 'Intruso')->assertNotFound();
            $this->actingAs($ajeno)->postJson(route('mensajes.leidos', $pedido))->assertNotFound();
        }

        $this->assertSame(1, Mensaje::count());
    }

    public function test_los_pedidos_terminados_son_de_solo_lectura(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();

        foreach ([Pedido::CONFIRMADO, Pedido::CANCELADO, Pedido::RECHAZADO] as $estado) {
            $pedido = $this->pedido($servicio, $cliente, $estado);
            Mensaje::create(['pedido_id' => $pedido->id, 'emisor_id' => $cliente->id, 'contenido' => 'Mensaje previo']);

            $this->enviar($cliente, $pedido, 'Ya no debería poder')->assertForbidden();

            $this->leer($cliente, $pedido)
                ->assertOk()
                ->assertJsonPath('puede_escribir', false)
                ->assertJsonPath('mensajes.0.contenido', 'Mensaje previo');
        }

        // En disputa la conversación sigue abierta: es cuando más hace falta.
        $enDisputa = $this->pedido($servicio, $cliente, Pedido::EN_DISPUTA);
        $this->enviar($cliente, $enDisputa, 'Sigo esperando respuesta')->assertCreated();
    }

    public function test_valida_el_contenido(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);

        $this->enviar($cliente, $pedido, '')->assertStatus(422)->assertJsonValidationErrors('contenido');
        $this->enviar($cliente, $pedido, '    ')->assertStatus(422)->assertJsonValidationErrors('contenido');
        $this->enviar($cliente, $pedido, str_repeat('a', 201))->assertStatus(422)->assertJsonValidationErrors('contenido');

        $this->assertSame(0, Mensaje::count());

        $this->enviar($cliente, $pedido, str_repeat('a', 200))->assertCreated();
    }

    public function test_el_texto_se_guarda_tal_cual_sin_interpretarlo(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);

        $this->enviar($cliente, $pedido, '<script>alert(1)</script>')
            ->assertCreated()
            ->assertJsonPath('mensaje.contenido', '<script>alert(1)</script>');
    }

    /* ------------------------------- cola offline ------------------------------- */

    public function test_reintentar_con_la_misma_clave_no_duplica_el_mensaje(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);

        $primero = $this->enviar($cliente, $pedido, 'Escrito sin señal', self::CLAVE)->assertCreated();

        $reintento = $this->enviar($cliente, $pedido, 'Escrito sin señal', self::CLAVE)
            ->assertOk()
            ->assertJsonPath('duplicado', true);

        $this->assertSame($primero->json('mensaje.id'), $reintento->json('mensaje.id'));
        $this->assertSame(1, Mensaje::count());
    }

    /* ------------------------------- lectura y contador ------------------------------- */

    public function test_marcar_leidos_alimenta_el_visto_del_emisor(): void
    {
        [$prestador, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);

        $this->enviar($cliente, $pedido, 'Uno');
        $segundo = $this->enviar($cliente, $pedido, 'Dos');

        $this->leer($prestador, $pedido)->assertJsonPath('leidos_hasta', null);

        $this->actingAs($prestador)->postJson(route('mensajes.leidos', $pedido))->assertOk()->assertJsonPath('marcados', 2);
        $this->actingAs($prestador)->postJson(route('mensajes.leidos', $pedido))->assertOk()->assertJsonPath('marcados', 0);

        $vista = $this->leer($cliente, $pedido)
            ->assertOk()
            ->assertJsonPath('leidos_hasta', $segundo->json('mensaje.id'));

        $this->assertSame([true, true], array_column($vista->json('mensajes'), 'leido'));
    }

    public function test_el_contador_cuenta_solo_lo_que_me_enviaron_sin_leer(): void
    {
        [$prestador, , $servicio] = $this->prestador();
        $cliente  = $this->cliente();
        $otro     = $this->cliente();
        $pedido   = $this->pedido($servicio, $cliente);
        $pedido2  = $this->pedido($servicio, $otro);

        $this->enviar($cliente, $pedido, 'Uno');
        $this->enviar($cliente, $pedido, 'Dos');
        $this->enviar($prestador, $pedido, 'Respuesta');
        $this->enviar($otro, $pedido2, 'Otro pedido');

        $this->actingAs($prestador)->getJson(route('chat.noLeidos'))
            ->assertOk()
            ->assertJsonPath("no_leidos.{$pedido->id}", 2)
            ->assertJsonPath("no_leidos.{$pedido2->id}", 1)
            ->assertJsonPath('total', 3);

        $this->actingAs($cliente)->getJson(route('chat.noLeidos'))
            ->assertOk()
            ->assertJsonPath("no_leidos.{$pedido->id}", 1)
            ->assertJsonPath('total', 1);

        // Quien no participa no ve nada.
        $ajeno = $this->actingAs($this->cliente())->getJson(route('chat.noLeidos'))->assertOk()->assertJsonPath('total', 0);
        $this->assertSame([], $ajeno->json('no_leidos'));

        // Al leer, el contador baja.
        $this->actingAs($prestador)->postJson(route('mensajes.leidos', $pedido))->assertOk();
        $this->actingAs($prestador)->getJson(route('chat.noLeidos'))->assertJsonPath('total', 1);
    }

    /* ------------------------------- avisos push ------------------------------- */

    public function test_cada_mensaje_dispara_un_push_sin_mostrar_el_texto(): void
    {
        config(['services.webpush.public_key' => 'pub', 'services.webpush.private_key' => 'priv']);
        Notification::fake();

        [$prestador, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);

        // El push es el tiempo real del chat: sale en CADA mensaje, no uno cada tanto.
        $this->enviar($cliente, $pedido, 'Primer mensaje secreto');
        $this->enviar($cliente, $pedido, 'Segundo mensaje');

        Notification::assertSentToTimes($prestador, NuevoMensajeChat::class, 2);
        Notification::assertNotSentTo($cliente, NuevoMensajeChat::class);

        $aviso = (new NuevoMensajeChat($pedido->id))->toWebPush($prestador);
        $this->assertStringNotContainsString('secreto', json_encode($aviso));
        $this->assertSame('/dashboard?chat='.$pedido->id, $aviso['url']);
        $this->assertSame('chat', $aviso['tipo'], 'El Service Worker lo usa para avisar a la app abierta.');
        $this->assertSame($pedido->id, $aviso['pedido_id']);
        $this->assertSame('chat-'.$pedido->id, $aviso['tag']);
    }

    public function test_un_reintento_con_la_misma_clave_no_manda_otro_push(): void
    {
        config(['services.webpush.public_key' => 'pub', 'services.webpush.private_key' => 'priv']);
        Notification::fake();

        [$prestador, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);

        $this->enviar($cliente, $pedido, 'Desde la cola offline', self::CLAVE)->assertCreated();
        $this->enviar($cliente, $pedido, 'Desde la cola offline', self::CLAVE)->assertOk();

        Notification::assertSentToTimes($prestador, NuevoMensajeChat::class, 1);
    }

    public function test_la_lectura_informa_si_el_servidor_puede_enviar_push(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente);

        // El cliente web usa este dato para decidir con qué frecuencia consulta.
        config(['services.webpush.public_key' => 'pub', 'services.webpush.private_key' => 'priv']);
        $this->leer($cliente, $pedido)->assertOk()->assertJsonPath('push', true);

        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);
        $this->leer($cliente, $pedido)->assertOk()->assertJsonPath('push', false);
    }

    public function test_un_mensaje_se_guarda_aunque_el_push_no_este_configurado(): void
    {
        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);

        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();

        $this->enviar($cliente, $this->pedido($servicio, $cliente), 'Hola')->assertCreated();
        $this->assertSame(1, Mensaje::count());
    }

    /* ------------------------------- retención ------------------------------- */

    public function test_la_purga_borra_solo_chats_de_pedidos_terminados_y_vencidos(): void
    {
        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();

        $viejoTerminado   = $this->pedido($servicio, $cliente, Pedido::CONFIRMADO);
        $recienteTerminado = $this->pedido($servicio, $cliente, Pedido::CONFIRMADO);
        $viejoActivo      = $this->pedido($servicio, $cliente, Pedido::EN_PROGRESO);
        $viejoEnDisputa   = $this->pedido($servicio, $cliente, Pedido::EN_DISPUTA);

        foreach ([$viejoTerminado, $recienteTerminado, $viejoActivo, $viejoEnDisputa] as $pedido) {
            Mensaje::create(['pedido_id' => $pedido->id, 'emisor_id' => $cliente->id, 'contenido' => 'Hola']);
        }

        // updated_at se fuerza con una consulta directa: Eloquent lo pisaría con la fecha de hoy.
        DB::table('pedidos')
            ->whereIn('id', [$viejoTerminado->id, $viejoActivo->id, $viejoEnDisputa->id])
            ->update(['updated_at' => now()->subDays(200)]);

        $this->artisan('chat:purgar-mensajes', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(4, Mensaje::count(), 'El modo prueba no debe borrar nada.');

        $this->artisan('chat:purgar-mensajes')->assertSuccessful();

        $this->assertSame(3, Mensaje::count());
        $this->assertFalse(Mensaje::where('pedido_id', $viejoTerminado->id)->exists());
        $this->assertTrue(Mensaje::where('pedido_id', $recienteTerminado->id)->exists(), 'Terminado hace poco: se conserva.');
        $this->assertTrue(Mensaje::where('pedido_id', $viejoActivo->id)->exists(), 'Pedido activo: se conserva.');
        $this->assertTrue(Mensaje::where('pedido_id', $viejoEnDisputa->id)->exists(), 'En disputa: se conserva.');

        $this->assertSame(1, DB::table('log_auditoria')->where('accion', 'chat.mensajes_purgados')->count());
    }

    public function test_la_purga_no_borra_nada_con_una_retencion_invalida(): void
    {
        config(['marketplace.chat.retencion_dias' => 0]);

        [, , $servicio] = $this->prestador();
        $cliente = $this->cliente();
        $pedido  = $this->pedido($servicio, $cliente, Pedido::CONFIRMADO);
        Mensaje::create(['pedido_id' => $pedido->id, 'emisor_id' => $cliente->id, 'contenido' => 'Hola']);
        DB::table('pedidos')->where('id', $pedido->id)->update(['updated_at' => now()->subYears(2)]);

        $this->artisan('chat:purgar-mensajes')->assertFailed();

        $this->assertSame(1, Mensaje::count());
    }
}
