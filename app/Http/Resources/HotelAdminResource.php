<?php

namespace App\Http\Resources;

use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Hotel
 */
class HotelAdminResource extends JsonResource
{
    use HasPortadaUrl;

    public function toArray(Request $request): array
    {
        return [
            'id_hotel' => $this->id_hotel,
            'nombre' => $this->nombre,
            'rnt' => $this->rnt,
            'celular' => $this->celular,
            'correo' => $this->correo,
            'nombre_contacto' => $this->nombre_contacto,
            'calificacion_salud' => $this->calificacion_salud,
            'direccion' => $this->whenLoaded('direccion', fn () => $this->direccion?->direccion),
            'url_foto' => $this->portadaUrl(),
            'is_visible' => (bool) $this->is_visible,
        ];
    }
}
