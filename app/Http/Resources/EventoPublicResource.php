<?php

namespace App\Http\Resources;

use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Evento
 */
class EventoPublicResource extends JsonResource
{
    use HasPortadaUrl;

    public function toArray(Request $request): array
    {
        return [
            'id_evento' => $this->id_evento,
            'nombre' => $this->nombre,
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin' => $this->fecha_fin,
            'tipo' => $this->tipo,
            'organizador' => $this->organizador,
            'direccion' => $this->whenLoaded('direccion', fn () => $this->direccion?->direccion),
            'url_foto' => $this->portadaUrl(),
            'is_visible' => (bool) $this->is_visible,
            'cancelado' => (bool) $this->cancelado,
            'motivo_cancelacion' => $this->motivo_cancelacion,
            'estado' => $this->estado,
        ];
    }
}
