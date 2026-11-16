<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class RespaldoTest extends TestCase
{
    /**
     * Sin token el middleware superadmin debe bloquear.
     */
    public function test_respaldo_exige_superadmin(): void
    {
        $this->getJson('/api/admin/respaldo/fotos/inventario')->assertStatus(401);
        $this->getJson('/api/admin/respaldo/fotos')->assertStatus(401);
    }

    /**
     * Inventario vacío cuando no hay fotos.
     */
    public function test_inventario_vacio(): void
    {
        $this->withoutMiddleware();

        Storage::fake('public');

        $this->getJson('/api/admin/respaldo/fotos/inventario')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 0);
    }

    /**
     * Inventario cuenta por carpeta e ignora .gitkeep.
     */
    public function test_inventario_cuenta_por_carpeta(): void
    {
        $this->withoutMiddleware();

        Storage::fake('public');
        Storage::disk('public')->put('fotos/hoteles/a.jpg', 'x');
        Storage::disk('public')->put('fotos/hoteles/b.jpg', 'yy');
        Storage::disk('public')->put('fotos/eventos/c.jpg', 'zzz');
        Storage::disk('public')->put('fotos/.gitkeep', '');

        $this->getJson('/api/admin/respaldo/fotos/inventario')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.por_carpeta.hoteles', 2)
            ->assertJsonPath('data.por_carpeta.eventos', 1);
    }

    /**
     * Descarga ZIP con la estructura fotos/{carpeta}/...
     */
    public function test_descarga_zip_con_estructura(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZIP no disponible en este entorno.');
        }

        $this->withoutMiddleware();

        Storage::fake('public');
        Storage::disk('public')->put('fotos/hoteles/a.jpg', 'contenido-a');
        Storage::disk('public')->put('fotos/eventos/c.jpg', 'contenido-c');

        $respuesta = $this->get('/api/admin/respaldo/fotos');
        $respuesta->assertOk();
        $respuesta->assertHeader('content-type', 'application/zip');

        $temporal = tempnam(sys_get_temp_dir(), 'respaldo-test').'.zip';
        file_put_contents($temporal, $respuesta->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($temporal));
        $nombres = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombres[] = $zip->getNameIndex($i);
        }
        $zip->close();
        unlink($temporal);

        sort($nombres);
        $this->assertSame(['fotos/eventos/c.jpg', 'fotos/hoteles/a.jpg'], $nombres);
    }

    /**
     * Sin fotos la descarga responde 404.
     */
    public function test_descarga_sin_fotos_404(): void
    {
        $this->withoutMiddleware();

        Storage::fake('public');

        $this->getJson('/api/admin/respaldo/fotos')->assertStatus(404);
    }

    /**
     * El comando exige --from y --to.
     */
    public function test_comando_exige_from_y_to(): void
    {
        $this->artisan('fotos:reescribir-url')
            ->assertFailed();
    }
}
