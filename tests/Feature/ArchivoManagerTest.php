<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateJwt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivoManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_rutas_exigen_token(): void
    {
        $this->getJson('/api/archivos')->assertStatus(401);
        $this->postJson('/api/archivos', [])->assertStatus(401);
        $this->getJson('/api/archivos/cualquiera.pdf/descargar')->assertStatus(401);
        $this->deleteJson('/api/archivos/cualquiera.pdf')->assertStatus(401);
    }

    public function test_flujo_subir_listar_descargar_eliminar(): void
    {
        $this->withoutMiddleware(AuthenticateJwt::class);

        $subida = $this->postJson('/api/archivos', [
            'archivo' => UploadedFile::fake()->create('acta.pdf', 100, 'application/pdf'),
        ]);

        $subida->assertStatus(201)->assertJson(['success' => true]);
        $nombre = $subida->json('data.nombre');
        $this->assertIsString($nombre);
        Storage::disk('local')->assertExists('archivos/'.$nombre);

        $this->getJson('/api/archivos')
            ->assertOk()
            ->assertJsonFragment(['nombre' => $nombre]);

        $descarga = $this->get('/api/archivos/'.urlencode($nombre).'/descargar');
        $descarga->assertOk();

        $this->deleteJson('/api/archivos/'.urlencode($nombre))
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Archivo eliminado']);

        Storage::disk('local')->assertMissing('archivos/'.$nombre);
    }

    public function test_rechaza_tipo_no_permitido_y_descarga_inexistente(): void
    {
        $this->withoutMiddleware(AuthenticateJwt::class);

        $this->postJson('/api/archivos', [
            'archivo' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
        ])->assertStatus(422);

        $this->getJson('/api/archivos/no-existe.pdf/descargar')->assertStatus(404);
        $this->deleteJson('/api/archivos/no-existe.pdf')->assertStatus(404);
    }
}
