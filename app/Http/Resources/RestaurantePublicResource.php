<?php

namespace App\Http\Resources;

use App\Models\Restaurante;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Restaurante
 */
class RestaurantePublicResource extends JsonResource
{
    use HasPortadaUrl;

    public function toArray(Request $request): array
    {
        return [
            'id_restaurante' => $this->id_restaurante,
            'nombre' => $this->nombre,
            'direccion' => $this->whenLoaded('direccion', fn () => $this->direccion?->direccion),
            'celular' => $this->celular,
            'redes_sociales' => $this->redesSocialesArray(),
            'url_foto' => $this->portadaUrl(),
            'id_tipo_cocina' => $this->id_tipo_cocina,
            'tipo_cocina' => $this->whenLoaded('tipoCocina', fn () => $this->tipoCocina?->nombre),
        ];
    }
}
