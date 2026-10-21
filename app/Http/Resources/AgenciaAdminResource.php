<?php

namespace App\Http\Resources;

use App\Models\Agencia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agencia
 */
class AgenciaAdminResource extends JsonResource
{
    use HasPortadaUrl;

    public function toArray(Request $request): array
    {
        return [
            'id_agencia' => $this->id_agencia,
            'nombre' => $this->nombre,
            'nit' => $this->nit,
            'rnt' => $this->rnt,
            'celular' => $this->celular,
            'correo' => $this->correo,
            'representante_legal' => $this->representante_legal,
            'url_foto' => $this->portadaUrl(),
            'id_tipo_agencia' => $this->id_tipo_agencia,
            'tipo_agencia' => $this->whenLoaded('tipoAgencia', fn () => $this->tipoAgencia?->nombre),
            'is_visible' => (bool) $this->is_visible,
        ];
    }
}
