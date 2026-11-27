<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\PerfilPrestador;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Búsqueda por cercanía (docs/decisiones/ADR-001): bounding box + Haversine sobre columnas
 * latitud/longitud. Corre igual en MySQL, PostgreSQL y SQLite (en SQLite se registran las
 * funciones matemáticas que ese motor no trae).
 */
class GeolocalizacionTest extends TestCase
{
    use RefreshDatabase;

    /** Zócalo de la Ciudad de México. */
    private const LAT = 19.4326;
    private const LNG = -99.1332;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() === 'sqlite') {
            $pdo = DB::connection()->getPdo();
            $pdo->sqliteCreateFunction('RADIANS', 'deg2rad', 1);
            $pdo->sqliteCreateFunction('COS', 'cos', 1);
            $pdo->sqliteCreateFunction('SIN', 'sin', 1);
            $pdo->sqliteCreateFunction('ACOS', 'acos', 1);
            $pdo->sqliteCreateFunction('LEAST', fn (...$v) => min($v), -1);
        }
    }

    /** Distancia de referencia calculada en PHP (misma fórmula, radio 6371 km). */
    private function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 6371 * 2 * asin(sqrt($a));
    }

    private function servicioEn(?float $lat, ?float $lng, int $radioCobertura = 15, string $titulo = 'Servicio'): Servicio
    {
        $user   = User::factory()->create();
        $perfil = PerfilPrestador::create([
            'user_id'             => $user->id,
            'estado_verificacion' => 'verificado',
            'latitud'             => $lat,
            'longitud'            => $lng,
            'radio_cobertura_km'  => $radioCobertura,
        ]);

        return Servicio::create([
            'perfil_prestador_id' => $perfil->id,
            'categoria_id'        => Categoria::firstOrCreate(['nombre' => 'Plomería'])->id,
            'titulo'              => $titulo,
            'descripcion'         => 'Descripción de prueba.',
            'precio_base'         => 300,
            'activo'              => true,
        ]);
    }

    private function cercanos(float $km): \Illuminate\Support\Collection
    {
        return Servicio::query()->cercanosA(self::LAT, self::LNG, $km)->get();
    }

    public function test_solo_aparecen_los_prestadores_dentro_del_radio_pedido(): void
    {
        $cerca = $this->servicioEn(self::LAT + 0.045, self::LNG, titulo: 'A 5 km');        // ≈ 5.0 km al norte
        $medio = $this->servicioEn(self::LAT, self::LNG + 0.09, titulo: 'A 9 km');          // ≈ 9.4 km al este
        $lejos = $this->servicioEn(self::LAT + 0.18, self::LNG, titulo: 'A 20 km');         // ≈ 20 km

        $ids = $this->cercanos(10)->pluck('id')->all();

        $this->assertContains($cerca->id, $ids);
        $this->assertContains($medio->id, $ids);
        $this->assertNotContains($lejos->id, $ids);
        $this->assertContains($lejos->id, $this->cercanos(25)->pluck('id')->all());
    }

    public function test_respeta_el_radio_de_cobertura_que_declaro_el_prestador(): void
    {
        // A 5 km, pero solo atiende hasta 3 km a la redonda: no debe salir aunque el cliente busque a 10 km.
        $estrecho = $this->servicioEn(self::LAT + 0.045, self::LNG, radioCobertura: 3);
        $amplio   = $this->servicioEn(self::LAT + 0.045, self::LNG, radioCobertura: 15);

        $ids = $this->cercanos(10)->pluck('id')->all();

        $this->assertNotContains($estrecho->id, $ids);
        $this->assertContains($amplio->id, $ids);
    }

    public function test_los_prestadores_sin_ubicacion_no_aparecen_en_busquedas_por_cercania(): void
    {
        $sinUbicacion = $this->servicioEn(null, null);

        $this->assertNotContains($sinUbicacion->id, $this->cercanos(50)->pluck('id')->all());
    }

    public function test_el_mismo_punto_da_distancia_cero_y_no_falla(): void
    {
        // Regresión: ACOS(1.0000000000000002) lanza error en PostgreSQL; el LEAST(1, …) lo evita.
        $mismo = $this->servicioEn(self::LAT, self::LNG);

        $resultado = $this->cercanos(5)->firstWhere('id', $mismo->id);

        $this->assertNotNull($resultado);
        $this->assertEqualsWithDelta(0.0, (float) $resultado->distancia_km, 0.1);
    }

    public function test_la_distancia_es_correcta_y_esta_redondeada_a_un_decimal(): void
    {
        $servicio = $this->servicioEn(self::LAT + 0.045, self::LNG);
        $esperado = $this->km(self::LAT, self::LNG, self::LAT + 0.045, self::LNG);

        $resultado = $this->cercanos(10)->firstWhere('id', $servicio->id);

        $this->assertEqualsWithDelta($esperado, (float) $resultado->distancia_km, 0.1);
        $this->assertEqualsWithDelta(round((float) $resultado->distancia_km, 1), (float) $resultado->distancia_km, 0.0001);
    }

    public function test_ordena_del_mas_cercano_al_mas_lejano(): void
    {
        $tercero = $this->servicioEn(self::LAT + 0.08, self::LNG, titulo: 'C');
        $primero = $this->servicioEn(self::LAT + 0.01, self::LNG, titulo: 'A');
        $segundo = $this->servicioEn(self::LAT + 0.04, self::LNG, titulo: 'B');

        $this->assertSame(
            [$primero->id, $segundo->id, $tercero->id],
            $this->cercanos(20)->pluck('id')->all()
        );
    }

    public function test_el_catalogo_publico_filtra_por_cercania_y_nunca_expone_coordenadas(): void
    {
        $cerca = $this->servicioEn(self::LAT + 0.045, self::LNG, titulo: 'Cerca de ti');
        $this->servicioEn(self::LAT + 0.18, self::LNG, titulo: 'Muy lejos');

        $respuesta = $this->get(route('servicios.index', ['lat' => self::LAT, 'lng' => self::LNG, 'radio_km' => 10]))->assertOk();

        $servicios = $respuesta->viewData('page')['props']['servicios']['data'];
        $this->assertSame([$cerca->id], array_column($servicios, 'id'));

        $json = json_encode($respuesta->viewData('page')['props']);
        $this->assertStringNotContainsString('latitud', $json);
        $this->assertStringNotContainsString('longitud', $json);
    }

    public function test_el_catalogo_valida_las_coordenadas_y_el_radio(): void
    {
        $this->get(route('servicios.index', ['lat' => 95, 'lng' => 0]))->assertSessionHasErrors('lat');
        $this->get(route('servicios.index', ['lat' => 19, 'lng' => 200]))->assertSessionHasErrors('lng');
        $this->get(route('servicios.index', ['lat' => 19]))->assertSessionHasErrors('lng');
        $this->get(route('servicios.index', ['lat' => 19, 'lng' => -99, 'radio_km' => 999]))->assertSessionHasErrors('radio_km');
    }
}
