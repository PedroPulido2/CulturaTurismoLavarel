<?php

namespace App\Http\Resources;

use App\Models\Guia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Guia
 */
class GuiaPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_guia' => $this->id_guia,
            'nombre' => $this->nombre,
            'num_tarjeta_profesional' => $this->num_tarjeta_profesional,
            'rnt' => $this->rnt,
            'celular' => $this->celular,
            'correo' => $this->correo,
            'idiomas' => $this->idiomas,
            'asociacion' => $this->asociacion,
            'principales_atractivos' => $this->principales_atractivos,
            'descripcion' => $this->descripcion,
            'id_especialidad' => $this->id_especialidad,
            'especialidad' => $this->whenLoaded('especialidad', fn () => $this->especialidad?->nombre),
        ];
    }
}
