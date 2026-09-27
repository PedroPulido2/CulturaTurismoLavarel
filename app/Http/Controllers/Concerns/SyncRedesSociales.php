<?php

namespace App\Http\Controllers\Concerns;

use App\Models\RedesSocial;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

trait SyncRedesSociales
{
    /**
     * Reemplaza las filas del pivote de redes sociales de una entidad
     * (tablas *_redes_sociales).
     * La PK del pivote es (entidad, red, url), por eso la url es obligatoria
     * y se eliminan duplicados antes de insertar.
     *
     * @param  array|null  $redes  [['id_redes_sociales' => int, 'url' => string], ...]
     */
    protected function syncRedesSociales(string $tablaPivote, string $columnaEntidad, $idEntidad, $redes): void
    {
        DB::table($tablaPivote)->where($columnaEntidad, $idEntidad)->delete();

        if (empty($redes)) {
            return;
        }

        $vistos = [];
        $filas = [];

        foreach ($redes as $red) {
            $clave = $red['id_redes_sociales'].'|'.$red['url'];
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $filas[] = [
                $columnaEntidad => $idEntidad,
                'id_redes_sociales' => $red['id_redes_sociales'],
                'url' => $red['url'],
            ];
        }

        if (! empty($filas)) {
            DB::table($tablaPivote)->insert($filas);
        }
    }

    /**
     * Reglas de validación para el arreglo de redes sociales de una entidad.
     */
    protected function reglasRedesSociales(): array
    {
        return [
            'redes_sociales' => 'sometimes|array',
            'redes_sociales.*.id_redes_sociales' => ['required', 'integer', Rule::exists(RedesSocial::class, 'id_redes_sociales')],
            'redes_sociales.*.url' => 'required|string|max:255',
        ];
    }
}
