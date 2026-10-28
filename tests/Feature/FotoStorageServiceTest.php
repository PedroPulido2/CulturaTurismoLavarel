<?php

namespace Tests\Feature;

use App\Services\FotoStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class FotoStorageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['app.url' => 'http://test.local']);
    }

    public function test_guardar_crea_el_archivo_y_retorna_url_absoluta(): void
    {
        $servicio = app(FotoStorageService::class);
        $archivo = UploadedFile::fake()->create('hotel.jpg', 120, 'image/jpeg');

        $url = $servicio->guardar($archivo, 'hoteles', 'hotel_H1');

        $this->assertStringStartsWith('http://test.local/storage/fotos/hoteles/', $url);
        $this->assertStringEndsWith('.jpg', $url);
        $this->assertTrue(Storage::disk('public')->exists($this->relativa($url)));
    }

    public function test_guardar_genera_nombres_unicos(): void
    {
        $servicio = app(FotoStorageService::class);

        $primera = $servicio->guardar(UploadedFile::fake()->create('a.jpg', 120, 'image/jpeg'), 'hoteles', 'hotel_H1');
        $segunda = $servicio->guardar(UploadedFile::fake()->create('a.jpg', 120, 'image/jpeg'), 'hoteles', 'hotel_H1');

        $this->assertNotSame($primera, $segunda);
    }

    public function test_guardar_rechaza_carpeta_no_permitida(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(FotoStorageService::class)->guardar(
            UploadedFile::fake()->create('a.jpg', 120, 'image/jpeg'),
            '../../secreto',
            'hotel_H1'
        );
    }

    public function test_eliminar_borra_el_archivo_local(): void
    {
        $servicio = app(FotoStorageService::class);
        $url = $servicio->guardar(UploadedFile::fake()->create('a.jpg', 120, 'image/jpeg'), 'eventos', 'evento_E1');

        $servicio->eliminar($url);

        $this->assertFalse(Storage::disk('public')->exists($this->relativa($url)));
    }

    public function test_eliminar_ignora_urls_no_locales_sin_explotar(): void
    {
        $servicio = app(FotoStorageService::class);

        $servicio->eliminar(null);
        $servicio->eliminar('');
        $servicio->eliminar('https://ejemplo.com/imagen.jpg');
        $servicio->eliminar('https://test.local/storage/../.env');
        $servicio->eliminar('http://test.local/storage/fotos/hoteles/inexistente.jpg');

        $this->assertTrue(true);
    }

    private function relativa(string $url): string
    {
        $pos = strpos($url, '/storage/');

        $this->assertNotFalse($pos);

        return substr($url, $pos + strlen('/storage/'));
    }
}
