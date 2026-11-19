<?php

namespace Tests\Feature;

use App\Console\Commands\MigrarDriveALocal;
use App\Services\FotoStorageService;
use App\Services\GoogleDriveService;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class DriveFotosTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_extraer_id_drive_desde_webviewlink(): void
    {
        $servicio = new GoogleDriveService;

        $this->assertSame(
            '1AbC2dEfGh',
            $servicio->extraerIdDrive('https://drive.google.com/file/d/1AbC2dEfGh/view?usp=sharing')
        );
    }

    public function test_extraer_id_drive_retorna_null_si_no_hay_id(): void
    {
        $servicio = new GoogleDriveService;

        $this->assertNull($servicio->extraerIdDrive('https://ejemplo.com/imagen.jpg'));
        $this->assertNull($servicio->extraerIdDrive(null));
        $this->assertNull($servicio->extraerIdDrive(''));
    }

    public function test_guardar_en_modo_drive_sube_y_comparte(): void
    {
        config(['fotos.driver' => 'drive', 'fotos.carpetas.hoteles' => 'carpeta-drive-1']);

        $drive = Mockery::mock(GoogleDriveService::class);
        $drive->shouldReceive('subirArchivo')->once()->andReturn([
            'id' => 'ID1',
            'link' => 'https://drive.google.com/file/d/ID1/view',
        ]);
        $drive->shouldReceive('compartirEnlace')->once()->with('ID1');

        $servicio = new FotoStorageService($drive);
        $url = $servicio->guardar(UploadedFile::fake()->create('hotel.jpg', 120, 'image/jpeg'), 'hoteles', 'hotel_H1');

        $this->assertSame('https://drive.google.com/file/d/ID1/view', $url);
    }

    public function test_guardar_en_modo_drive_exige_carpeta_configurada(): void
    {
        config(['fotos.driver' => 'drive', 'fotos.carpetas.hoteles' => null]);

        $this->expectException(InvalidArgumentException::class);

        (new FotoStorageService(Mockery::mock(GoogleDriveService::class)))
            ->guardar(UploadedFile::fake()->create('hotel.jpg', 120, 'image/jpeg'), 'hoteles', 'hotel_H1');
    }

    public function test_eliminar_url_drive_borra_en_drive(): void
    {
        $drive = Mockery::mock(GoogleDriveService::class);
        $drive->shouldReceive('deleteFromDrive')->once()->with('https://drive.google.com/file/d/ID1/view');

        (new FotoStorageService($drive))->eliminar('https://drive.google.com/file/d/ID1/view');

        $this->assertTrue(true);
    }

    public function test_comando_rechaza_carpeta_invalida(): void
    {
        $this->artisan('fotos:migrar-local-a-drive', ['--carpeta' => 'inexistente'])
            ->assertFailed();
    }

    public function test_migrar_drive_a_local_rechaza_carpeta_invalida(): void
    {
        $this->artisan('fotos:migrar-drive-a-local', ['--carpeta' => 'inexistente'])
            ->assertFailed();
    }

    public function test_migrar_drive_a_local_sin_carpetas_configuradas(): void
    {
        $this->artisan('fotos:migrar-drive-a-local')
            ->assertSuccessful();
    }

    public function test_mapa_fk_cubre_todas_las_carpetas_de_fotos(): void
    {
        $esperadas = array_diff(
            FotoStorageService::CARPETAS,
            ['perfiles']
        );

        $this->assertEqualsCanonicalizing($esperadas, array_keys(MigrarDriveALocal::MAPA_FK));
    }
}
