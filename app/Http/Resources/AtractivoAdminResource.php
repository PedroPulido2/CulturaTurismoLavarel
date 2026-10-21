<?php

namespace App\Http\Resources;

use App\Models\AtractivoTuristico;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lista de gestión (admin): vitrina pública + contacto,
 * dirección e is_visible para la columna Estado.
 *
 * @mixin AtractivoTuristico
 */
class AtractivoAdminResource extends JsonResource
{
    use HasPortadaUrl;

    public function toArray(Request $request): array
    {
        return [
            'id_atractivo_turistico' => $this->id_atractivo_turistico,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
            'descripcion' => $this->descripcion,
            'telefono' => $this->telefono,
            'horario' => $this->horario,
            'precio' => $this->precio,
            'direccion' => $this->whenLoaded('direccion', fn () => $this->direccion?->direccion),
            'url_foto' => $this->portadaUrl(),
            'is_visible' => (bool) $this->is_visible,
        ];
    }
}
