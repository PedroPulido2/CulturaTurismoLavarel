<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Filtros server-side para los listados paginados.
 *
 * - ?search=texto: coincidencia parcial en las columnas indicadas.
 * - Filtros exactos: ?param=valor (solo se aplican si vienen no vacíos).
 *
 * El operador es ILIKE en PostgreSQL (case-insensitive) y LIKE en
 * sqlite/mysql, para que los tests en :memory: sigan pasando.
 */
trait AplicaFiltrosListado
{
    protected function terminoBusqueda(Request $request): ?string
    {
        $raw = trim((string) $request->query('search', ''));

        if ($raw === '') {
            return null;
        }

        return mb_substr($raw, 0, 100);
    }

    protected function operadorLike(): string
    {
        try {
            return DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        } catch (\Throwable) {
            return 'LIKE';
        }
    }

    /**
     * @param  mixed  $query  Eloquent builder
     * @param  string[]  $columnas  Columnas de texto donde buscar
     */
    protected function aplicarBusqueda($query, Request $request, array $columnas): void
    {
        $termino = $this->terminoBusqueda($request);

        if ($termino === null || $columnas === []) {
            return;
        }

        // Escapa % y _ para que se busquen literales.
        $escapado = addcslashes($termino, '%_\\');
        $patron = "%{$escapado}%";
        $operador = $this->operadorLike();

        $query->where(function ($q) use ($columnas, $patron, $operador) {
            foreach ($columnas as $i => $columna) {
                if ($i === 0) {
                    $q->where($columna, $operador, $patron);
                } else {
                    $q->orWhere($columna, $operador, $patron);
                }
            }
        });
    }

    /**
     * Ordenamiento ?sort= (alias ?ordering=) sobre columnas permitidas.
     *
     * Sintaxis: ?sort=-calificacion_salud,nombre ("-" = DESC, resto ASC).
     * Solo se aplican columnas de la whitelist para evitar inyección en
     * el ORDER BY. Valores inválidos se ignoran sin romper el listado.
     *
     * calificacion_salud DESC usa NULLS LAST (en PostgreSQL el DESC
     * nativo pone los nulos primero); SQLite >= 3.30 soporta la misma
     * sintaxis, por lo que los tests en :memory: la cubren.
     *
     * @param  mixed  $query  Eloquent builder
     * @param  string[]  $permitidas  Columnas ordenables
     */
    protected function aplicarOrdenamiento($query, Request $request, array $permitidas): void
    {
        $raw = trim((string) $request->query('sort', $request->query('ordering', '')));

        if ($raw === '' || $permitidas === []) {
            return;
        }

        $permitidas = array_values(array_unique($permitidas));
        $aplicados = 0;

        foreach (explode(',', $raw) as $pedido) {
            $pedido = trim(mb_substr($pedido, 0, 60));

            if ($pedido === '') {
                continue;
            }

            $direccion = str_starts_with($pedido, '-') ? 'desc' : 'asc';
            $columna = ltrim($pedido, '+- ');

            if ($columna === '' || ! in_array($columna, $permitidas, true)) {
                continue;
            }

            if ($columna === 'calificacion_salud' && $direccion === 'desc') {
                $query->orderByRaw('calificacion_salud DESC NULLS LAST');
            } else {
                $query->orderBy($columna, $direccion);
            }

            $aplicados++;

            // Tope anti-abuso: bastan un par de criterios.
            if ($aplicados >= 3) {
                break;
            }
        }

        // Desempate determinista para que el paginado no baile.
        if ($aplicados > 0 && in_array('id_hotel', $permitidas, true)) {
            $query->orderBy('id_hotel', 'asc');
        }
    }

    /**
     * Filtro exacto ?param=valor sobre una columna.
     *
     * @param  mixed  $query  Eloquent builder
     */
    protected function aplicarFiltroExacto($query, Request $request, string $param, string $columna): void
    {
        if (! $request->has($param)) {
            return;
        }

        $valor = trim((string) $request->query($param));

        if ($valor === '') {
            return;
        }

        $query->where($columna, $valor);
    }
}
