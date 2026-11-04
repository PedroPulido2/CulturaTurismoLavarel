<?php

namespace Tests\Unit;

use App\Services\ArchivoStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class ArchivoStorageServiceTest extends TestCase
{
    private ArchivoStorageService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->servicio = new ArchivoStorageService;
    }

    public function test_guarda_y_lista_un_pdf(): void
    {
        $nombre = $this->servicio->guardar(
            UploadedFile::fake()->create('informe.pdf', 100, 'application/pdf')
        );

        $this->assertStringEndsWith('.pdf', $nombre);
        Storage::disk('local')->assertExists('archivos/'.$nombre);

        $nombres = array_column($this->servicio->listar(), 'nombre');
        $this->assertContains($nombre, $nombres);
    }

    public function test_rechaza_extension_no_permitida(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->servicio->guardar(
            UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream')
        );
    }

    public function test_rechaza_traversal_y_gitignore(): void
    {
        $this->assertNull($this->servicio->rutaAbsoluta('../.env'));
        $this->assertNull($this->servicio->rutaAbsoluta('.gitignore'));
        $this->assertFalse($this->servicio->eliminar('../../secret.txt'));
    }

    public function test_eliminar_borra_el_archivo(): void
    {
        $nombre = $this->servicio->guardar(
            UploadedFile::fake()->create('foto.png', 50, 'image/png')
        );

        $this->assertTrue($this->servicio->eliminar($nombre));
        Storage::disk('local')->assertMissing('archivos/'.$nombre);
        $this->assertFalse($this->servicio->eliminar($nombre));
    }
}
