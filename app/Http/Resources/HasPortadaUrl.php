<?php

namespace App\Http\Resources;

use Illuminate\Support\Collection;

trait HasPortadaUrl
{
    protected function portadaUrl(): ?string
    {
        $fotos = $this->whenLoaded('fotos', $this->fotos ?? collect());
        $fotos = $fotos instanceof Collection ? $fotos : collect($fotos);

        if ($fotos->isEmpty()) {
            return null;
        }

        return ($fotos->firstWhere('is_portada', true) ?? $fotos->first())->url_foto ?? null;
    }

    protected function redesSocialesArray(): array
    {
        $redes = $this->whenLoaded('redesSociales', $this->redesSociales ?? collect());
        $redes = $redes instanceof Collection ? $redes : collect($redes);

        return $redes->map(fn ($r) => [
            'id_redes_sociales' => $r->id_redes_sociales,
            'nombre' => $r->nombre,
            'url' => $r->pivot->url ?? null,
        ])->values()->all();
    }
}
