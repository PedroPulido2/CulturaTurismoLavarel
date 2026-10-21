<?php

namespace App\Http\Resources;

use App\Models\Agencia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Agencia no tiene dirección en el esquema: se omite.
 *
 * @mixin Agencia
 */
class AgenciaPublicResource extends JsonResource
{
    use HasPortadaUrl;

    public function toArray(Request $request): array
    {
        return [
            'id_agencia' => $this->id_agencia,
            'nombre' => $this->nombre,
            'celular' => $this->celular,
            'correo' => $this->correo,
            'redes_sociales' => $this->redesSocialesArray(),
            'url_foto' => $this->portadaUrl(),
        ];
    }
}
