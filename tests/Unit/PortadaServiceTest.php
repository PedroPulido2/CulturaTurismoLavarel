<?php

namespace Tests\Unit;

use App\Models\Foto;
use App\Services\PortadaService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PortadaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Requiere pdo_sqlite para emular el esquema culturayturismo.');
        }

        // La tabla real vive en el esquema culturayturismo (PostgreSQL).
        // En sqlite se emula adjuntando una BD en memoria con ese nombre.
        try {
            DB::statement('DETACH DATABASE culturayturismo');
        } catch (\Exception) {
            // Aún no adjuntada.
        }
        DB::statement("ATTACH DATABASE ':memory:' AS culturayturismo");
        DB::statement('CREATE TABLE culturayturismo.fotos (
            id_foto INTEGER PRIMARY KEY AUTOINCREMENT,
            url_foto TEXT NULL,
            is_portada INTEGER DEFAULT 0,
            id_hotel TEXT NULL,
            id_atractivo_turistico TEXT NULL,
            id_restaurante TEXT NULL,
            id_agencia TEXT NULL,
            id_servicio_cultural INTEGER NULL,
            id_evento INTEGER NULL
        )');
    }

    private function crearFoto(string $fk, $idEntidad, bool $portada = false): Foto
    {
        return Foto::create([
            'url_foto' => 'https://drive.test/foto',
            'is_portada' => $portada,
            $fk => $idEntidad,
        ]);
    }

    public function test_buscar_respeta_entidad(): void
    {
        $foto = $this->crearFoto('id_atractivo_turistico', 'ATR1');
        $servicio = app(PortadaService::class);

        $this->assertNotNull($servicio->buscar('id_atractivo_turistico', 'ATR1', $foto->id_foto));
        $this->assertNull($servicio->buscar('id_atractivo_turistico', 'OTRO', $foto->id_foto));
        $this->assertNull($servicio->buscar('id_hotel', 'H1', $foto->id_foto));
        $this->assertNull($servicio->buscar('id_atractivo_turistico', 'ATR1', 99999));
    }

    public function test_asignar_deja_una_sola_portada(): void
    {
        $a = $this->crearFoto('id_hotel', 'H1', true);
        $b = $this->crearFoto('id_hotel', 'H1');
        $otraEntidad = $this->crearFoto('id_hotel', 'H2', true);

        app(PortadaService::class)->asignar('id_hotel', 'H1', $b->id_foto);

        $this->assertFalse(Foto::find($a->id_foto)->is_portada);
        $this->assertTrue(Foto::find($b->id_foto)->is_portada);
        // La otra entidad no se toca.
        $this->assertTrue(Foto::find($otraEntidad->id_foto)->is_portada);
        $this->assertSame(1, Foto::where('id_hotel', 'H1')->where('is_portada', true)->count());
    }

    public function test_asegurar_promueve_la_mas_antigua_si_no_hay_portada(): void
    {
        $a = $this->crearFoto('id_evento', 5);
        $b = $this->crearFoto('id_evento', 5);

        app(PortadaService::class)->asegurar('id_evento', 5);

        $this->assertTrue(Foto::find($a->id_foto)->is_portada);
        $this->assertFalse(Foto::find($b->id_foto)->is_portada);
    }

    public function test_asegurar_no_cambia_si_ya_hay_portada(): void
    {
        $a = $this->crearFoto('id_evento', 5);
        $b = $this->crearFoto('id_evento', 5, true);

        app(PortadaService::class)->asegurar('id_evento', 5);

        $this->assertFalse(Foto::find($a->id_foto)->is_portada);
        $this->assertTrue(Foto::find($b->id_foto)->is_portada);
    }

    public function test_asegurar_sin_fotos_no_falla(): void
    {
        app(PortadaService::class)->asegurar('id_agencia', 'SIN-FOTOS');

        $this->assertSame(0, Foto::where('id_agencia', 'SIN-FOTOS')->count());
    }
}
