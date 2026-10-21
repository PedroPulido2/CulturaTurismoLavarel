<?php

namespace App\Http\Resources;

use App\Models\ServicioCultural;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lista de gestión (admin): pública + datos de contacto.
 * (La tabla no tiene is_visible: no hay columna Estado.)
 *
 * @mixin ServicioCultural
 */
class ServicioCulturalAdminResource extends JsonResource
{
    use HasPortadaUrl;

    public function toArray(Request $request): array
    {
        return [
            'id_servicio_cultural' => $this->id_servicio_cultural,
            'nombre' => $this->nombre,
            'nit' => $this->nit,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'contacto' => $this->contacto,
            'biografia' => $this->biografia,
            'reconocimientos' => $this->reconocimientos,
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
