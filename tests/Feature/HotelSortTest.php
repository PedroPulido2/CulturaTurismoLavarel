<?php

namespace Tests\Feature;

use App\Models\Hotel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HotelSortTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            return;
        }

        try {
            DB::statement("ATTACH DATABASE ':memory:' AS culturayturismo");
        } catch (\Exception) {
            // Ya adjuntada en este proceso.
        }

        DB::statement('DROP TABLE IF EXISTS "culturayturismo"."hotel_redes_sociales"');
        DB::statement('DROP TABLE IF EXISTS "culturayturismo"."redes_sociales"');
        DB::statement('DROP TABLE IF EXISTS "culturayturismo"."fotos"');
        DB::statement('DROP TABLE IF EXISTS "culturayturismo"."hotel"');
        DB::statement('DROP TABLE IF EXISTS "culturayturismo"."direccion_google"');

        DB::statement('
            CREATE TABLE "culturayturismo"."direccion_google" (
                id_direccion INTEGER PRIMARY KEY AUTOINCREMENT,
                direccion TEXT NULL,
                latitud REAL NULL,
                longitud REAL NULL
            )
        ');
        DB::statement('
            CREATE TABLE "culturayturismo"."hotel" (
                id_hotel TEXT PRIMARY KEY,
                nombre TEXT NULL,
                celular INTEGER NULL,
                calificacion_salud REAL NULL,
                is_visible INTEGER NOT NULL DEFAULT 1,
                id_direccion INTEGER NULL
            )
        ');
        DB::statement('
            CREATE TABLE "culturayturismo"."fotos" (
                id_foto INTEGER PRIMARY KEY AUTOINCREMENT,
                url_foto TEXT NULL,
                is_portada INTEGER NULL,
                id_hotel TEXT NULL,
                id_atractivo_turistico TEXT NULL,
                id_restaurante TEXT NULL,
                id_agencia TEXT NULL,
                id_servicio_cultural INTEGER NULL,
                id_evento TEXT NULL
            )
        ');
        DB::statement('
            CREATE TABLE "culturayturismo"."redes_sociales" (
                id_redes_sociales INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre VARCHAR(100) NULL
            )
        ');
        DB::statement('
            CREATE TABLE "culturayturismo"."hotel_redes_sociales" (
                id_hotel TEXT NOT NULL,
                id_redes_sociales INTEGER NOT NULL,
                url TEXT NULL
            )
        ');

        Hotel::create(['id_hotel' => 'HOT-A', 'nombre' => 'Hotel A', 'calificacion_salud' => 4.9, 'is_visible' => true]);
        Hotel::create(['id_hotel' => 'HOT-B', 'nombre' => 'Hotel B', 'calificacion_salud' => 4.5, 'is_visible' => true]);
        Hotel::create(['id_hotel' => 'HOT-C', 'nombre' => 'Hotel C', 'calificacion_salud' => null, 'is_visible' => true]);
        Hotel::create(['id_hotel' => 'HOT-D', 'nombre' => 'Hotel Oculto', 'calificacion_salud' => 5.0, 'is_visible' => false]);
    }

    protected function tearDown(): void
    {
        if (in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            foreach (['hotel_redes_sociales', 'redes_sociales', 'fotos', 'hotel', 'direccion_google'] as $tabla) {
                Schema::dropIfExists("culturayturismo.{$tabla}");
            }
            try {
                DB::statement('DETACH DATABASE culturayturismo');
            } catch (\Exception) {
            }
        }

        parent::tearDown();
    }

    private function sinSqlite(): bool
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Requiere pdo_sqlite.');

            return true;
        }

        return false;
    }

    public function test_hotel_ordena_por_calificacion_desc_con_nulos_al_final(): void
    {
        if ($this->sinSqlite()) {
            return;
        }

        $response = $this->getJson('/api/hotel?sort=-calificacion_salud');

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame(
            ['HOT-A', 'HOT-B', 'HOT-C'],
            $response->json('data.*.id_hotel')
        );
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_ordering_como_alias_de_sort(): void
    {
        if ($this->sinSqlite()) {
            return;
        }

        $response = $this->getJson('/api/hotel?ordering=-calificacion_salud');

        $response->assertOk();
        $this->assertSame(
            ['HOT-A', 'HOT-B', 'HOT-C'],
            $response->json('data.*.id_hotel')
        );
    }

    public function test_sort_invalido_se_ignora_sin_romper(): void
    {
        if ($this->sinSqlite()) {
            return;
        }

        $this->getJson('/api/hotel?sort=-columna_inexistente')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson('/api/hotel?sort=calificacion_salud;DROP TABLE hotel')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_destacados_devuelve_ranking_con_default_8(): void
    {
        if ($this->sinSqlite()) {
            return;
        }

        $response = $this->getJson('/api/hotel/destacados');

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame(
            ['HOT-A', 'HOT-B', 'HOT-C'],
            $response->json('data.*.id_hotel')
        );
        $this->assertSame(8, $response->json('meta.per_page'));

        $top1 = $this->getJson('/api/hotel/destacados?per_page=2');

        $top1->assertOk();
        $this->assertSame(['HOT-A', 'HOT-B'], $top1->json('data.*.id_hotel'));
        $this->assertSame(2, $top1->json('meta.per_page'));
    }

    public function test_destacados_no_lo_captura_la_ruta_detalle(): void
    {
        if ($this->sinSqlite()) {
            return;
        }

        $response = $this->getJson('/api/hotel/destacados');

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertNotSame('Hotel no encontrado', $response->json('message'));
    }

    public function test_admin_acepta_sort(): void
    {
        if ($this->sinSqlite()) {
            return;
        }

        $this->withoutMiddleware();

        $response = $this->getJson('/api/admin/hotel?sort=-calificacion_salud');

        $response->assertOk()->assertJson(['success' => true]);
        // Admin sí incluye el oculto (5.0) primero, nulo al final.
        $this->assertSame(
            ['HOT-D', 'HOT-A', 'HOT-B', 'HOT-C'],
            $response->json('data.*.id_hotel')
        );
    }
}
