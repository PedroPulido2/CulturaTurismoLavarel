<?php

namespace App\Services;

use App\Models\Foto;

class PortadaService
{
    /**
     * Busca una foto verificando que pertenezca a la entidad indicada.
     */
    public function buscar(string $columnaEntidad, $idEntidad, int $idFoto): ?Foto
    {
        return Foto::where('id_foto', $idFoto)->where($columnaEntidad, $idEntidad)->first();
    }

    /**
     * Marca una foto como portada (y desmarca el resto de la entidad).
     * Debe llamarse con un id ya verificado con buscar().
     */
    public function asignar(string $columnaEntidad, $idEntidad, int $idFoto): void
    {
        Foto::where($columnaEntidad, $idEntidad)->update(['is_portada' => false]);
        Foto::where('id_foto', $idFoto)->where($columnaEntidad, $idEntidad)->update(['is_portada' => true]);
    }

    /**
     * Garantiza que la entidad tenga exactamente una portada si tiene fotos:
     * si ninguna está marcada (ej. se eliminó la portada), promueve la más antigua.
     */
    public function asegurar(string $columnaEntidad, $idEntidad): void
    {
        $hayPortada = Foto::where($columnaEntidad, $idEntidad)->where('is_portada', true)->exists();

        if (! $hayPortada) {
            Foto::where($columnaEntidad, $idEntidad)->orderBy('id_foto')->first()?->update(['is_portada' => true]);
        }
    }
}
