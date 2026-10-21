<?php

namespace App\Http\Resources;

use App\Models\ServicioCultural;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vitrina pública: solo columnas del esquema necesarias
 * para la tarjeta. Sin nit/telefono/correo/reconocimientos.
 * (La tabla no tiene is_visible: no hay filtro de visibilidad.)
 *
 * @mixin ServicioCultural
 */
class ServicioCulturalPublicResource extends JsonResource
{
    use HasPortadaUrl;

    public function toArray(Request $request): array
    {
        return [
            'id_servicio_cultural' => $this->id_servicio_cultural,
            'nombre' => $this->nombre,
            'biografia' => $this->biografia,
            'contacto' => $this->contacto,
            'id_tipo_servicio' => $this->id_tipo_servicio,
            'tipo_servicio' => $this->whenLoaded('tipoServicio', fn () => $this->tipoServicio?->nombre),
            'id_area_artistica' => $this->id_area_artistica,
            'area_artistica' => $this->whenLoaded('areaArtistica', fn () => $this->areaArtistica?->nombre),
            'id_publico_dirigido' => $this->id_publico_dirigido,
            'publico_dirigido' => $this->whenLoaded('publicoDirigido', fn () => $this->publicoDirigido?->nombre),
            'url_foto' => $this->portadaUrl(),
        ];
    }
}
