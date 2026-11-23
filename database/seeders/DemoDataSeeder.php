<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\NivelesServicio;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PerfilPrestador;
use App\Models\Resena;
use App\Models\Servicio;
use App\Models\Transaccione;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de ejemplo para desarrollo: clientes, prestadores en distintos estados
 * de verificación, servicios, pedidos en varios estados, una reseña y pagos.
 *
 * Todas las cuentas usan la contraseña "password". No se ejecuta en producción.
 * Es idempotente: se puede correr varias veces sin duplicar nada.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $crearUsuario = function (string $nombre, string $email, string $rol) use ($password): User {
            $usuario = User::firstOrCreate(
                ['email' => $email],
                ['nombre' => $nombre, 'password' => $password]
            );
            $usuario->forceFill(['email_verified_at' => now()])->save();
            $usuario->syncRoles([$rol]);

            return $usuario;
        };

        /* ---------------- Clientes ---------------- */
        $clientes = [
            $crearUsuario('Carlos Martínez Gómez', 'cliente1@example.com', 'cliente'),
            $crearUsuario('Jeremy Roldán', 'cliente2@example.com', 'cliente'),
            $crearUsuario('Mela Peláez', 'cliente3@example.com', 'cliente'),
        ];

        /* ---------------- Prestadores ---------------- */
        $perfil = function (User $usuario, array $datos): PerfilPrestador {
            $perfil = PerfilPrestador::firstOrCreate(['user_id' => $usuario->id], $datos);
            // es_pro lo gobierna la suscripción: no es asignable en masa.
            $perfil->forceFill(['es_pro' => false])->save();

            return $perfil;
        };

        $ian = $perfil(
            $crearUsuario('Ian Hernández', 'prestador1@example.com', 'prestador'),
            [
                'descripcion'          => 'Profesional con más de 5 años de experiencia.',
                'ubicacion_aproximada' => 'Puebla, Puebla',
                'latitud'              => 19.206,
                'longitud'             => -98.391,
                'radio_cobertura_km'   => 10,
                'estado_verificacion'  => PerfilPrestador::ESTADO_VERIFICADO,
            ]
        );

        $jose = $perfil(
            $crearUsuario('José López', 'prestador2@example.com', 'prestador'),
            [
                'descripcion'          => 'Electricista certificado, atiendo emergencias.',
                'ubicacion_aproximada' => 'Cholula, Puebla',
                'latitud'              => 19.064,
                'longitud'             => -98.304,
                'radio_cobertura_km'   => 15,
                'estado_verificacion'  => PerfilPrestador::ESTADO_VERIFICADO,
            ]
        );

        $perfil(
            $crearUsuario('Pepe Pecas', 'prestador3@example.com', 'prestador'),
            [
                'descripcion'         => 'Pintor de interiores y exteriores.',
                'estado_verificacion' => PerfilPrestador::ESTADO_PENDIENTE,
            ]
        );

        $perfil(
            $crearUsuario('Juan Pérez', 'prestador4@example.com', 'prestador'),
            [
                'descripcion'         => 'Jardinería y poda.',
                'estado_verificacion' => PerfilPrestador::ESTADO_RECHAZADO,
                'motivo_rechazo'      => 'Documentación incompleta.',
            ]
        );

        /* ---------------- Servicios ---------------- */
        $categoria = fn (string $slug) => Categoria::where('slug', $slug)->firstOrFail();

        $cableado = Servicio::firstOrCreate(
            ['perfil_prestador_id' => $jose->id, 'titulo' => 'Cambio de cableado eléctrico'],
            [
                'categoria_id' => $categoria('electricidad')->id,
                'descripcion'  => 'Instalación de cableado en casa habitación cumpliendo la normativa mexicana.',
                'precio_base'  => 900.00,
                'activo'       => true,
            ]
        );

        $tuberia = Servicio::firstOrCreate(
            ['perfil_prestador_id' => $ian->id, 'titulo' => 'Reparación de tubería hidráulica y drenaje'],
            [
                'categoria_id' => $categoria('plomeria-y-gas')->id,
                'descripcion'  => 'Sustitución de tubería dañada y destape de drenaje.',
                'precio_base'  => 1500.00,
                'activo'       => true,
            ]
        );

        Servicio::firstOrCreate(
            ['perfil_prestador_id' => $ian->id, 'titulo' => 'Cambio de chapa y llaves'],
            [
                'categoria_id' => $categoria('cerrajeria')->id,
                'descripcion'  => 'Cambio e instalación de chapas de seguridad.',
                'precio_base'  => 650.00,
                'activo'       => false,
            ]
        );

        foreach ([
            ['basico', 900, 60, 'Revisión y cambio de un circuito.'],
            ['completo', 1800, 120, 'Cableado de una habitación completa.'],
            ['avanzado', 3500, 240, 'Cableado de toda la casa con tablero nuevo.'],
        ] as [$nivel, $precio, $minutos, $detalles]) {
            NivelesServicio::firstOrCreate(
                ['servicio_id' => $cableado->id, 'nivel' => $nivel],
                ['precio' => $precio, 'duracion_min' => $minutos, 'detalles' => $detalles]
            );
        }

        /* ---------------- Pedidos (uno por estado relevante) ---------------- */
        if (Pedido::exists()) {
            return; // ya hay pedidos: no se duplican en ejecuciones posteriores
        }

        $pedido = fn (User $cliente, Servicio $servicio, string $estado, int $dias, string $dir) => Pedido::create([
            'cliente_id'         => $cliente->id,
            'servicio_id'        => $servicio->id,
            'estado'             => $estado,
            'direccion_servicio' => $dir,
            'fecha_programada'   => now()->addDays($dias)->startOfDay(),
        ]);

        [$c1, $c2, $c3] = $clientes;

        $pedido($c1, $tuberia, Pedido::PENDIENTE, 3, 'Calle 5 Norte No. 13');
        $aceptado = $pedido($c2, $cableado, Pedido::ACEPTADO, 2, 'Av. Reforma 120');
        $pedido($c3, $tuberia, Pedido::EN_PROGRESO, 0, 'Privada 3 Sur 45');
        $pedido($c2, $cableado, Pedido::RECHAZADO, 5, 'Calle 123');
        $pedido($c3, $cableado, Pedido::CANCELADO, 4, 'Calle 9 Poniente 8');
        $pedido($c1, $tuberia, Pedido::COMPLETADO, -1, 'Calle 5 Norte No. 13');
        $confirmado = $pedido($c1, $cableado, Pedido::CONFIRMADO, -3, 'Calle 5 Norte No. 13');

        /* ---------------- Reseña, pagos y movimientos ---------------- */
        Resena::create([
            'pedido_id'    => $confirmado->id,
            'cliente_id'   => $confirmado->cliente_id,
            'servicio_id'  => $confirmado->servicio_id,
            'calificacion' => 5,
            'comentario'   => 'Excelente trabajo, puntual y limpio.',
        ]);

        // Pago completado del pedido confirmado, ya liberado (12 % de comisión).
        $pago = Pago::forceCreate([
            'pedido_id'      => $confirmado->id,
            'monto'          => 900.00,
            'metodo_pago'    => 'simulado',
            'estado'         => 'completado',
            'transaccion_id' => 'SIM-DEMO000000000001',
        ]);
        foreach ([['cobro_cliente', 900.00], ['liberacion_prestador', 792.00], ['comision_plataforma', 108.00]] as [$tipo, $monto]) {
            Transaccione::create(['pago_id' => $pago->id, 'tipo' => $tipo, 'monto' => $monto, 'creada_en' => now()]);
        }

        // Pago pendiente de un pedido aceptado.
        Pago::forceCreate([
            'pedido_id'      => $aceptado->id,
            'monto'          => 900.00,
            'metodo_pago'    => 'mercadopago',
            'estado'         => 'pendiente',
            'transaccion_id' => 'PREF-DEMO-0001',
        ]);
    }
}
