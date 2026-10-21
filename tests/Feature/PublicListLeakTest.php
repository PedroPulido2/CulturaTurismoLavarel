<?php

namespace Tests\Feature;

use App\Http\Resources\AgenciaPublicResource;
use App\Http\Resources\AtractivoPublicResource;
use App\Http\Resources\EventoPublicResource;
use App\Http\Resources\GuiaPublicResource;
use App\Http\Resources\HotelPublicResource;
use App\Http\Resources\RestaurantePublicResource;
use App\Models\Agencia;
use App\Models\AtractivoTuristico;
use App\Models\DireccionGoogle;
use App\Models\Especialidad;
use App\Models\Evento;
use App\Models\Foto;
use App\Models\Guia;
use App\Models\Hotel;
use App\Models\RedesSocial;
use App\Models\Restaurante;
use App\Models\TipoCocina;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PublicListLeakTest extends TestCase
{
    private function red(string $nombre, string $url): RedesSocial
    {
        $red = new RedesSocial(['nombre' => $nombre]);
        $red->id_redes_sociales = 1;
        $red->pivot = (object) ['url' => $url];

        return $red;
    }

    private function fotos(array $urls, string $fk, string $fkValue): Collection
    {
        return collect($urls)->map(function ($url, $i) use ($fk, $fkValue) {
            $foto = new Foto(['url_foto' => $url, 'is_portada' => $i === 0]);
            $foto->id_foto = $i + 1;
            $foto->setAttribute($fk, $fkValue);

            return $foto;
        });
    }

    public function test_atractivo_solo_campos_esquema(): void
    {
        $request = Request::create('/', 'GET');
        $atractivo = new AtractivoTuristico([
            'nombre' => 'Pantano de Vargas', 'tipo' => 'Histórico',
            'descripcion' => 'Monumento', 'horario' => '8-17',
            'telefono' => 123, 'precio' => 'Gratis', 'is_visible' => true,
        ]);
        $atractivo->id_atractivo_turistico = 'ATR001';
        $atractivo->setRelation('fotos', $this->fotos(['http://img/1.jpg', 'http://img/2.jpg'], 'id_atractivo_turistico', 'ATR001'));

        $data = (new AtractivoPublicResource($atractivo))->toArray($request);

        $this->assertSame(
            ['id_atractivo_turistico', 'nombre', 'tipo', 'descripcion', 'horario', 'url_foto'],
            array_keys($data)
        );
        $this->assertSame('http://img/1.jpg', $data['url_foto']);
    }

    public function test_hotel_oculta_correo_contacto_y_observacion(): void
    {
        $request = Request::create('/', 'GET');
        $hotel = new Hotel([
            'nombre' => 'H', 'celular' => 3001234567, 'correo' => 'h@x.com',
            'nombre_contacto' => 'Pedro', 'observacion' => 'interna',
            'rnt' => 'RNT1', 'calificacion_salud' => 4.5,
        ]);
        $hotel->id_hotel = 'HOT001';
        $direccion = new DireccionGoogle(['direccion' => 'Calle 1', 'latitud' => 1.0, 'longitud' => 2.0]);
        $direccion->id_direccion = 1;
        $hotel->setRelation('direccion', $direccion);
        $hotel->setRelation('fotos', $this->fotos(['http://img/h.jpg'], 'id_hotel', 'HOT001'));
        $hotel->setRelation('redesSociales', collect([$this->red('facebook', 'http://fb/x')]));

        $data = (new HotelPublicResource($hotel))->toArray($request);

        // lat/long exactas no se exponen: solo el string de dirección
        $this->assertSame('Calle 1', $data['direccion']);
        $this->assertArrayNotHasKey('correo', $data);
        $this->assertArrayNotHasKey('nombre_contacto', $data);
        $this->assertArrayNotHasKey('observacion', $data);
        $this->assertArrayNotHasKey('rnt', $data);
        $this->assertSame([['id_redes_sociales' => 1, 'nombre' => 'facebook', 'url' => 'http://fb/x']], $data['redes_sociales']);
    }

    public function test_restaurante_expone_tipo_cocina_y_oculta_propietario(): void
    {
        $request = Request::create('/', 'GET');
        $r = new Restaurante(['nombre' => 'R', 'celular' => 3001, 'correo' => 'r@x.com', 'propietario' => 'X', 'capacidad' => '10']);
        $r->id_restaurante = 'RES001';
        $r->id_tipo_cocina = 3;
        $direccion = new DireccionGoogle(['direccion' => 'Cra 2']);
        $direccion->id_direccion = 2;
        $tipo = new TipoCocina(['nombre' => 'Típica']);
        $tipo->id_tipo_cocina = 3;
        $r->setRelation('direccion', $direccion);
        $r->setRelation('tipoCocina', $tipo);
        $r->setRelation('fotos', $this->fotos(['http://img/r.jpg'], 'id_restaurante', 'RES001'));
        $r->setRelation('redesSociales', collect());

        $data = (new RestaurantePublicResource($r))->toArray($request);

        $this->assertSame(3, $data['id_tipo_cocina']);
        $this->assertSame('Típica', $data['tipo_cocina']);
        $this->assertArrayNotHasKey('correo', $data);
        $this->assertArrayNotHasKey('propietario', $data);
        $this->assertArrayNotHasKey('capacidad', $data);
    }

    public function test_agencia_sin_direccion_con_correo_y_portada(): void
    {
        $request = Request::create('/', 'GET');
        $a = new Agencia(['nombre' => 'A', 'celular' => 3002, 'correo' => 'a@x.com', 'nit' => 'NIT', 'rnt' => 'RNT']);
        $a->id_agencia = 'AGE001';
        $a->setRelation('fotos', $this->fotos(['http://img/a.jpg'], 'id_agencia', 'AGE001'));
        $a->setRelation('redesSociales', collect());

        $data = (new AgenciaPublicResource($a))->toArray($request);

        $this->assertSame(['id_agencia', 'nombre', 'celular', 'correo', 'redes_sociales', 'url_foto'], array_keys($data));
    }

    public function test_guia_sin_cedula_y_con_especialidad(): void
    {
        $request = Request::create('/', 'GET');
        $g = new Guia([
            'nombre' => 'G', 'n_cedula' => 123456, 'rnt' => 'R', 'celular' => 3003,
            'correo' => 'g@x.com', 'num_tarjeta_profesional' => 99, 'idiomas' => 'ES-EN',
            'asociacion' => 'ASO', 'principales_atractivos' => 'P', 'descripcion' => 'D',
            'rango_anios_experiencia' => '5-10',
        ]);
        $g->id_guia = 'GUI001';
        $g->id_especialidad = 5;
        $esp = new Especialidad(['nombre' => 'Aventura']);
        $esp->id_especialidad = 5;
        $g->setRelation('especialidad', $esp);

        $data = (new GuiaPublicResource($g))->toArray($request);

        $this->assertArrayNotHasKey('n_cedula', $data);
        $this->assertArrayNotHasKey('rango_anios_experiencia', $data);
        $this->assertArrayNotHasKey('competencia', $data);
        $this->assertSame('Aventura', $data['especialidad']);
        $this->assertSame('D', $data['descripcion']);
    }

    public function test_evento_varchar_sin_codigo_y_con_fecha_fin(): void
    {
        $evento = new Evento;
        $this->assertFalse($evento->getIncrementing());
        $this->assertSame('string', $evento->getKeyType());
        $this->assertContains('id_evento', $evento->getFillable());
        $this->assertNotContains('codigo', $evento->getFillable());

        $request = Request::create('/', 'GET');
        $e = new Evento([
            'nombre' => 'E', 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-03',
            'tipo' => 'Feria', 'organizador' => 'Alcaldía', 'contacto' => 3004,
            'asistentes_estimados' => 500, 'observaciones' => 'internas', 'is_visible' => true,
        ]);
        $e->id_evento = 'EVT001';
        $direccion = new DireccionGoogle(['direccion' => 'Plaza']);
        $direccion->id_direccion = 9;
        $e->setRelation('direccion', $direccion);
        $e->setRelation('fotos', $this->fotos(['http://img/e.jpg'], 'id_evento', 'EVT001'));

        $data = (new EventoPublicResource($e))->toArray($request);

        $this->assertSame('EVT001', $data['id_evento']);
        $this->assertSame('2026-11-03', $data['fecha_fin']);
        $this->assertArrayNotHasKey('codigo', $data);
        $this->assertArrayNotHasKey('contacto', $data);
        $this->assertArrayNotHasKey('asistentes_estimados', $data);
        $this->assertArrayNotHasKey('observaciones', $data);
    }
}
