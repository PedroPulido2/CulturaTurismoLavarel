<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServicioCulturalVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sin auth real: el middleware 'modulo' exige JWT + permiso en PG.
        $this->withoutMiddleware();

        // Este entorno no tiene pdo_sqlite (solo pgsql); los casos con BD
        // se omiten aquí y corren donde sqlite esté disponible (CI/dev).
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            return;
        }

        // La tabla real vive en el esquema PG 'culturayturismo.*'.
        // En sqlite :memory: se emula con una BD adjunta del mismo nombre.
        DB::statement("ATTACH DATABASE ':memory:' AS culturayturismo");

        DB::statement('DROP TABLE IF EXISTS "culturayturismo"."servicio_cultural"');
        DB::statement('
            CREATE TABLE "culturayturismo"."servicio_cultural" (
                id_servicio_cultural INTEGER PRIMARY KEY,
                nit INTEGER NULL,
                nombre VARCHAR(255) NULL,
                telefono INTEGER NULL,
                correo VARCHAR(255) NULL,
                contacto VARCHAR(255) NULL,
                biografia TEXT NULL,
                reconocimientos TEXT NULL,
                is_visible BOOLEAN NOT NULL DEFAULT 1,
                id_tipo_servicio INTEGER NULL,
                id_publico_dirigido INTEGER NULL,
                id_area_artistica INTEGER NULL
            )
        ');

        DB::table('culturayturismo.servicio_cultural')->insert([
            'id_servicio_cultural' => 1,
            'nombre' => 'Carranga Los Andes',
            'correo' => 'grupo@ejemplo.com',
            'is_visible' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            Schema::dropIfExists('culturayturismo.servicio_cultural');
            DB::statement('DETACH DATABASE culturayturismo');
        }

        parent::tearDown();
    }

    public function test_ruta_patch_visibility_registrada(): void
    {
        $route = collect(Route::getRoutes())
            ->first(fn ($r) => in_array('PATCH', $r->methods())
                && $r->uri() === 'api/cultural-services/{id}/visibility');

        $this->assertNotNull($route, 'No existe PATCH api/cultural-services/{id}/visibility');
        $this->assertStringContainsString(
            'ServicioCulturalController@updateVisibility',
            $route->getActionName()
        );
        $this->assertContains('modulo:Servicios Culturales', $route->gatherMiddleware());
    }

    public function test_actualiza_visibilidad(): void
    {
        $this->markTestSkippedIfSinSqlite();
        $response = $this->patchJson('/api/cultural-services/1/visibility', [
            'is_visible' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id_servicio_cultural' => 1,
                    'is_visible' => false,
                ],
            ]);

        $this->assertDatabaseHas('culturayturismo.servicio_cultural', [
            'id_servicio_cultural' => 1,
            'is_visible' => false,
        ]);
    }

    public function test_retorna_404_si_no_existe(): void
    {
        $this->markTestSkippedIfSinSqlite();
        $response = $this->patchJson('/api/cultural-services/999/visibility', [
            'is_visible' => true,
        ]);

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    public function test_retorna_400_sin_campo_is_visible(): void
    {
        $this->markTestSkippedIfSinSqlite();
        $response = $this->patchJson('/api/cultural-services/1/visibility', []);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    private function markTestSkippedIfSinSqlite(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Requiere pdo_sqlite (no instalado en este entorno).');
        }
    }
}
