<?php

namespace App\Console\Commands;

use App\Models\Foto;
use App\Models\Usuario;
use App\Services\FotoStorageService;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MigrarLocalADrive extends Command
{
    protected $signature = 'fotos:migrar-local-a-drive
        {--dry-run : Solo muestra qué haría, sin subir ni modificar la DB}
        {--carpeta= : Migrar solo una carpeta (hoteles, atractivos, restaurantes, agencias, eventos, servicios, perfiles)}';

    protected $description = 'Sube las fotos locales a sus carpetas de Drive y reescribe fotos.url_foto y usuario.url_foto con el webViewLink';

    public function __construct(private GoogleDriveService $drive)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $soloCarpeta = $this->option('carpeta');
        $dryRun = (bool) $this->option('dry-run');

        $carpetas = $soloCarpeta
            ? [$soloCarpeta]
            : FotoStorageService::CARPETAS;

        foreach ($carpetas as $carpeta) {
            if (! in_array($carpeta, FotoStorageService::CARPETAS, true)) {
                $this->error("Carpeta no válida: {$carpeta}");

                return self::FAILURE;
            }
        }

        try {
            Foto::query()->limit(1)->count();
            Usuario::query()->limit(1)->count();
        } catch (\Throwable $e) {
            $this->error('No se pudo leer la base de datos: '.$e->getMessage());

            return self::FAILURE;
        }

        $totales = ['subidas' => 0, 'vinculadas' => 0, 'omitidas_drive' => 0, 'huerfanas' => 0, 'faltantes' => 0, 'errores' => 0];

        foreach ($carpetas as $carpeta) {
            $this->migrarCarpeta($carpeta, $dryRun, $totales);
        }

        $this->info(sprintf(
            'Resumen: %d subidas, %d filas vinculadas, %d ya en Drive, %d huérfanas, %d archivos faltantes, %d errores.',
            $totales['subidas'],
            $totales['vinculadas'],
            $totales['omitidas_drive'],
            $totales['huerfanas'],
            $totales['faltantes'],
            $totales['errores']
        ));

        if ($dryRun) {
            $this->info('Dry-run: no se subió nada ni se modificó la DB.');
        }

        return $totales['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function migrarCarpeta(string $carpeta, bool $dryRun, array &$totales): void
    {
        $idCarpetaDrive = config("fotos.carpetas.{$carpeta}");

        if (! is_string($idCarpetaDrive) || $idCarpetaDrive === '') {
            $this->warn("Sin carpeta Drive configurada para {$carpeta}, se omite.");

            return;
        }

        $archivos = $this->archivosLocales($carpeta);
        $nombresLocales = array_map('basename', $archivos);

        $this->line(sprintf('Carpeta %s: %d archivos locales.', $carpeta, count($archivos)));

        $barra = $this->output->createProgressBar(count($archivos));
        $barra->start();

        foreach ($archivos as $relativa) {
            $nombre = basename($relativa);

            try {
                $filas = $this->filasQueApuntan("fotos/{$carpeta}/{$nombre}");

                if ($filas === []) {
                    $totales['huerfanas']++;
                    $this->line("\nHuérfana (sin fila en DB): {$relativa}");
                } else {
                    $pendientes = array_filter($filas, fn ($f) => ! str_contains((string) $f->url_foto, 'drive.google.com'));

                    if ($pendientes === []) {
                        $totales['omitidas_drive']++;
                    } elseif ($dryRun) {
                        $totales['subidas']++;
                        $totales['vinculadas'] += count($pendientes);
                    } else {
                        $absoluta = Storage::disk('public')->path($relativa);
                        $resultado = $this->drive->subirArchivo(
                            $absoluta,
                            mime_content_type($absoluta) ?: 'application/octet-stream',
                            $nombre,
                            $idCarpetaDrive
                        );
                        $this->drive->compartirEnlace($resultado['id']);

                        foreach ($pendientes as $fila) {
                            $fila->url_foto = $resultado['link'];
                            $fila->save();
                            $totales['vinculadas']++;
                        }
                        $totales['subidas']++;
                    }
                }
            } catch (\Throwable $e) {
                $totales['errores']++;
                $this->line("\nError con {$relativa}: ".$e->getMessage());
            }

            $barra->advance();
        }

        $barra->finish();
        $this->newLine();

        $this->reportarFaltantes($carpeta, $nombresLocales, $totales);
    }

    /**
     * Filas (fotos + perfiles) cuya URL local termina en la ruta dada.
     *
     * @return list<Model>
     */
    private function filasQueApuntan(string $sufijo): array
    {
        $like = '%/'.ltrim($this->escaparLike($sufijo), '/');

        $fotos = Foto::query()->where('url_foto', 'like', $like)->get()->all();
        $usuarios = Usuario::query()->where('url_foto', 'like', $like)->get()->all();

        return array_merge($fotos, $usuarios);
    }

    /**
     * Filas con URL local cuyo archivo ya no existe en disco.
     */
    private function reportarFaltantes(string $carpeta, array $nombresLocales, array &$totales): void
    {
        $prefijo = '%/fotos/'.ltrim($this->escaparLike($carpeta), '/').'/';

        $revisar = Foto::query()->where('url_foto', 'like', $prefijo.'%')
            ->where('url_foto', 'not like', '%drive.google.com%')
            ->get(['url_foto']);

        if ($carpeta === 'perfiles') {
            $revisar = $revisar->concat(
                Usuario::query()->where('url_foto', 'like', $prefijo.'%')
                    ->where('url_foto', 'not like', '%drive.google.com%')
                    ->get(['url_foto'])
            );
        }

        foreach ($revisar as $fila) {
            $nombre = basename((string) $fila->url_foto);

            if (! in_array($nombre, $nombresLocales, true)) {
                $totales['faltantes']++;
                $this->line("Falta archivo local (re-subir por el front): {$fila->url_foto}");
            }
        }
    }

    /**
     * @return list<string> rutas relativas bajo fotos/{carpeta}/
     */
    private function archivosLocales(string $carpeta): array
    {
        try {
            if (! Storage::disk('public')->exists("fotos/{$carpeta}")) {
                return [];
            }

            $todos = Storage::disk('public')->allFiles("fotos/{$carpeta}");
        } catch (\Throwable) {
            return [];
        }

        $fotos = array_values(array_filter(
            $todos,
            fn (string $ruta) => ! in_array(basename($ruta), ['.gitignore', '.gitkeep'], true)
        ));

        sort($fotos);

        return $fotos;
    }

    private function escaparLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }
}
