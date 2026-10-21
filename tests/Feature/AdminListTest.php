<?php

namespace Tests\Feature;

use App\Http\Resources\HotelAdminResource;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminListTest extends TestCase
{
    public function test_admin_sin_token_es_401(): void
    {
        $this->getJson('/api/admin/tourism')->assertStatus(401);
        $this->getJson('/api/admin/hotel')->assertStatus(401);
        $this->getJson('/api/admin/restaurant')->assertStatus(401);
        $this->getJson('/api/admin/agency')->assertStatus(401);
        $this->getJson('/api/admin/guide')->assertStatus(401);
        $this->getJson('/api/admin/event')->assertStatus(401);
    }

    public function test_hotel_admin_expone_estado_y_contacto(): void
    {
        $hotel = new Hotel([
            'nombre' => 'H', 'rnt' => 'RNT1', 'celular' => 3001234567,
            'correo' => 'h@x.com', 'nombre_contacto' => 'Pedro',
            'calificacion_salud' => 4.5, 'is_visible' => false,
        ]);
        $hotel->id_hotel = 'HOT001';
        $hotel->setRelation('direccion', null);
        $hotel->setRelation('fotos', collect());
        $hotel->setRelation('redesSociales', collect());

        $data = (new HotelAdminResource($hotel))->toArray(Request::create('/', 'GET'));

        // Columna Estado de la tabla admin
        $this->assertArrayHasKey('is_visible', $data);
        $this->assertFalse($data['is_visible']);
        // Columnas Contacto de la tabla admin
        $this->assertSame(3001234567, $data['celular']);
        $this->assertSame('h@x.com', $data['correo']);
    }
}
