<?php

namespace App\Http\Resources;

use App\Models\Guia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Guia
 */
class GuiaAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_guia' => $this->id_guia,
            'nombre' => $this->nombre,
            'n_cedula' => $this->n_cedula,
            'rnt' => $this->rnt,
            'celular' => $this->celular,
            'correo' => $this->correo,
            'num_tarjeta_profesional' => $this->num_tarjeta_profesional,
            'idiomas' => $this->idiomas,
            'asociacion' => $this->asociacion,
            'principales_atractivos' => $this->principales_atractivos,
            'descripcion' => $this->descripcion,
            'id_especialidad' => $this->id_especialidad,
            'especialidad' => $this->whenLoaded('especialidad', fn () => $this->especialidad?->nombre),
            'is_visible' => (bool) $this->is_visible,
        ];
    }
}
