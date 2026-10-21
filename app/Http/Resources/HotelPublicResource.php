<?php

namespace App\Http\Resources;

use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Hotel
 */
class HotelPublicResource extends JsonResource
{
    use HasPortadaUrl;

    public function toArray(Request $request): array
    {
        return [
            'id_hotel' => $this->id_hotel,
            'nombre' => $this->nombre,
            'celular' => $this->celular,
            'direccion' => $this->whenLoaded('direccion', fn () => $this->direccion?->direccion),
            'calificacion_salud' => $this->calificacion_salud,
            'redes_sociales' => $this->redesSocialesArray(),
            'url_foto' => $this->portadaUrl(),
        ];
    }
}
