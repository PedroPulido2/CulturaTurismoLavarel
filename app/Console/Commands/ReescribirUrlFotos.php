<?php

namespace App\Console\Commands;

use App\Models\Foto;
use App\Models\Usuario;
use Illuminate\Console\Command;

class ReescribirUrlFotos extends Command
{
    protected $signature = 'fotos:reescribir-url
        {--from= : Prefijo de URL a reemplazar (ej. https://app.onrender.com)}
        {--to= : Nuevo prefijo (ej. http://localhost:8000)}
        {--dry-run : Solo muestra cuántos registros cambiarían, sin guardar}';

    protected $description = 'Reescribe el prefijo de APP_URL en culturayturismo.fotos.url_foto y usuario.url_foto tras migrar el deploy (Render -> producción final)';

    public function handle(): int
    {
        $from = rtrim(trim((string) $this->option('from')), '/');
        $to = rtrim(trim((string) $this->option('to')), '/');

        if ($from === '' || $to === '') {
            $this->error('Debe indicar --from y --to. Ejemplo: php artisan fotos:reescribir-url --from=https://app.onrender.com --to=http://localhost:8000');

            return self::FAILURE;
        }

        if ($from === $to) {
            $this->error('--from y --to son iguales, no hay nada que reescribir.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $conteoFotos = $this->contar(Foto::query()->where('url_foto', 'like', $from.'%'));
            $conteoUsuarios = $this->contar(Usuario::query()->where('url_foto', 'like', $from.'%'));
        } catch (\Throwable $e) {
            $this->error('No se pudo leer la base de datos: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("Dry-run: {$conteoFotos} fotos y {$conteoUsuarios} perfiles cambiarían de {$from} a {$to}.");

            return self::SUCCESS;
        }

        try {
            $fotos = $this->reescribir(Foto::query()->where('url_foto', 'like', $from.'%'), $from, $to, 'id_foto');
            $usuarios = $this->reescribir(Usuario::query()->where('url_foto', 'like', $from.'%'), $from, $to, 'id_usuario');
        } catch (\Throwable $e) {
            $this->error('Error reescribiendo URLs: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Listo: {$fotos} fotos y {$usuarios} perfiles actualizados de {$from} a {$to}.");

        return self::SUCCESS;
    }

    private function contar(mixed $query): int
    {
        return $query->count();
    }

    private function reescribir(mixed $query, string $from, string $to, string $columnaId): int
    {
        $actualizados = 0;

        $query->chunkById(500, function ($filas) use ($from, $to, &$actualizados) {
            foreach ($filas as $fila) {
                $nueva = str_replace($from, $to, (string) $fila->url_foto);

                if ($nueva !== $fila->url_foto) {
                    $fila->url_foto = $nueva;
                    $fila->save();
                    $actualizados++;
                }
            }
        }, $columnaId);

        return $actualizados;
    }
}
