<?php

namespace App\Http\Resources;

use App\Models\AtractivoTuristico;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vitrina pública de atractivos: solo columnas del esquema
 * que el front necesita para la tarjeta + detalle previo.
 *
 * @mixin AtractivoTuristico
 */
class AtractivoPublicResource extends JsonResource
{
    use HasPortadaUrl;

    public function toArray(Request $request): array
    {
        return [
            'id_atractivo_turistico' => $this->id_atractivo_turistico,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
            'descripcion' => $this->descripcion,
            'horario' => $this->horario,
            'url_foto' => $this->portadaUrl(),
        ];
    }
}
