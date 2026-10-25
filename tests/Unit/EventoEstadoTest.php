<?php

namespace Tests\Unit;

use App\Http\Resources\EventoAdminResource;
use App\Http\Resources\EventoPublicResource;
use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class EventoEstadoTest extends TestCase
{
    private function evento(array $attrs): Evento
    {
        return new Evento($attrs);
    }

    private function hoy(int $offsetDias = 0): string
    {
        return Carbon::today('America/Bogota')->addDays($offsetDias)->toDateString();
    }

    public function test_programado_si_inicio_futuro(): void
    {
        $e = $this->evento(['fecha_inicio' => $this->hoy(5), 'fecha_fin' => $this->hoy(7)]);

        $this->assertSame('programado', $e->estado);
    }

    public function test_en_curso_dentro_del_rango(): void
    {
        $e = $this->evento(['fecha_inicio' => $this->hoy(-1), 'fecha_fin' => $this->hoy(1)]);

        $this->assertSame('en_curso', $e->estado);
    }

    public function test_finalizado_si_fin_pasado(): void
    {
        $e = $this->evento(['fecha_inicio' => $this->hoy(-7), 'fecha_fin' => $this->hoy(-1)]);

        $this->assertSame('finalizado', $e->estado);
    }

    public function test_cancelado_precede_a_fechas(): void
    {
        $e = $this->evento([
            'fecha_inicio' => $this->hoy(5),
            'fecha_fin' => $this->hoy(7),
            'cancelado' => true,
        ]);

        $this->assertSame('cancelado', $e->estado);
    }

    public function test_sin_fecha_si_ambas_nulas(): void
    {
        $e = $this->evento([]);

        $this->assertSame('sin_fecha', $e->estado);
    }

    public function test_en_curso_con_fin_abierto(): void
    {
        $e = $this->evento(['fecha_inicio' => $this->hoy(-3), 'fecha_fin' => null]);

        $this->assertSame('en_curso', $e->estado);
    }

    public function test_resources_exponen_estado_cancelado_y_motivo(): void
    {
        $e = $this->evento([
            'id_evento' => 'EV-TEST',
            'nombre' => 'Prueba',
            'fecha_inicio' => $this->hoy(5),
            'fecha_fin' => $this->hoy(7),
            'cancelado' => true,
            'motivo_cancelacion' => 'Lluvia',
        ]);
        $req = Request::create('/', 'GET');
        $e->setRelation('fotos', collect());

        foreach ([
            (new EventoPublicResource($e))->toArray($req),
            (new EventoAdminResource($e))->toArray($req),
        ] as $data) {
            $this->assertSame('cancelado', $data['estado']);
            $this->assertTrue($data['cancelado']);
            $this->assertSame('Lluvia', $data['motivo_cancelacion']);
        }
    }
}
