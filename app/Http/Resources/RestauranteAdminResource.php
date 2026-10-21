<?php

namespace App\Http\Resources;

use App\Models\Restaurante;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Restaurante
 */
class RestauranteAdminResource extends JsonResource
{
    use HasPortadaUrl;

    public function toArray(Request $request): array
    {
        return [
            'id_restaurante' => $this->id_restaurante,
            'nombre' => $this->nombre,
            'celular' => $this->celular,
            'correo' => $this->correo,
            'direccion' => $this->whenLoaded('direccion', fn () => $this->direccion?->direccion),
            'url_foto' => $this->portadaUrl(),
            'id_tipo_cocina' => $this->id_tipo_cocina,
            'tipo_cocina' => $this->whenLoaded('tipoCocina', fn () => $this->tipoCocina?->nombre),
            'is_visible' => (bool) $this->is_visible,
        ];
    }
}
