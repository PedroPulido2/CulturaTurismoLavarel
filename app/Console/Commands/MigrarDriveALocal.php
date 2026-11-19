<?php

namespace App\Console\Commands;

use App\Models\Foto;
use App\Models\Usuario;
use App\Services\FotoStorageService;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class MigrarDriveALocal extends Command
{
    protected $signature = 'fotos:migrar-drive-a-local
        {--dry-run : Solo muestra qué haría, sin descargar ni modificar la DB}
        {--carpeta= : Migrar solo una carpeta (hoteles, atractivos, restaurantes, agencias, eventos, servicios, perfiles)}';

    protected $description = 'Descarga las fotos de Drive a storage/app/public/fotos/{carpeta}/ y reescribe fotos.url_foto y usuario.url_foto con la URL local';

    /**
     * Carpeta local -> columna FK que la identifica en la tabla fotos.
     */
    public const MAPA_FK = [
        'hoteles' => 'id_hotel',
        'atractivos' => 'id_atractivo_turistico',
        'restaurantes' => 'id_restaurante',
        'agencias' => 'id_agencia',
        'servicios' => 'id_servicio_cultural',
        'eventos' => 'id_evento',
    ];

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

        $carpetas = array_values(array_filter(
            $carpetas,
            fn (string $c) => is_string(config("fotos.carpetas.{$c}")) && config("fotos.carpetas.{$c}") !== ''
        ));

        if ($carpetas === []) {
            $this->warn('Sin carpetas Drive configuradas, no hay nada que migrar.');

            return self::SUCCESS;
        }

        try {
            Foto::query()->limit(1)->count();
            Usuario::query()->limit(1)->count();
        } catch (\Throwable $e) {
            $this->error('No se pudo leer la base de datos: '.$e->getMessage());

            return self::FAILURE;
        }

        $base = rtrim(config('app.url', 'http://localhost'), '/');
        $totales = ['descargadas' => 0, 'bytes' => 0, 'omitidas_local' => 0, 'faltantes' => 0, 'sin_id' => 0, 'errores' => 0];
        $escritos = [];

        foreach ($carpetas as $carpeta) {
            $this->migrarCarpeta($carpeta, $base, $dryRun, $totales, $escritos);
        }

        $this->info(sprintf(
            'Resumen: %d descargadas (%s), %d ya locales, %d faltantes en Drive, %d sin ID, %d errores.',
            $totales['descargadas'],
            $this->humano($totales['bytes']),
            $totales['omitidas_local'],
            $totales['faltantes'],
            $totales['sin_id'],
            $totales['errores']
        ));

        if ($dryRun) {
            $this->info('Dry-run: no se descargó nada ni se modificó la DB.');
        }

        return $totales['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function migrarCarpeta(string $carpeta, string $base, bool $dryRun, array &$totales, array &$escritos): void
    {
        $idCarpetaDrive = config("fotos.carpetas.{$carpeta}");

        try {
            $enDrive = $this->drive->listarArchivosEnCarpeta($idCarpetaDrive);
        } catch (\Throwable $e) {
            $totales['errores']++;
            $this->error("No se pudo listar Drive para {$carpeta}: ".$e->getMessage());

            return;
        }

        $porId = [];
        foreach ($enDrive as $a) {
            $porId[$a['id']] = $a;
        }

        $filas = $this->filasConUrlDrive($carpeta);

        $this->line(sprintf('Carpeta %s: %d archivos en Drive, %d filas con URL Drive.', $carpeta, count($enDrive), $filas->count()));

        $barra = $this->output->createProgressBar($filas->count());
        $barra->start();

        foreach ($filas as $fila) {
            try {
                $fileId = $this->drive->extraerIdDrive((string) $fila->url_foto);

                if ($fileId === null) {
                    $totales['sin_id']++;
                    $this->line("\nSin ID Drive en URL: {$fila->url_foto}");

                    continue;
                }

                if (! isset($porId[$fileId])) {
                    $totales['faltantes']++;
                    $this->line("\nFalta en Drive (se conserva la URL): {$fila->url_foto}");

                    continue;
                }

                $nombre = $porId[$fileId]['name'];
                $relativa = "fotos/{$carpeta}/{$nombre}";

                if (isset($escritos[$relativa]) && $escritos[$relativa] !== $fileId) {
                    $totales['errores']++;
                    $this->line("\nColisión: {$relativa} ya se escribió desde otro ID de Drive, se omite {$fila->url_foto}");

                    continue;
                }

                if ($dryRun) {
                    $totales['descargadas']++;
                    $totales['bytes'] += $porId[$fileId]['size'] ?? 0;

                    continue;
                }

                $bytes = $this->drive->descargarArchivo($fileId);
                Storage::disk('public')->put($relativa, $bytes);

                $fila->url_foto = $base.'/storage/'.$relativa;
                $fila->save();

                $escritos[$relativa] = $fileId;
                $totales['descargadas']++;
                $totales['bytes'] += strlen($bytes);
            } catch (\Throwable $e) {
                $totales['errores']++;
                $this->line("\nError con {$fila->url_foto}: ".$e->getMessage());
            }

            $barra->advance();
        }

        $barra->finish();
        $this->newLine();

        $omitidas = $this->contarLocales($carpeta);
        $totales['omitidas_local'] += $omitidas;
        $this->line("Carpeta {$carpeta}: {$omitidas} filas ya con URL local (omitidas).");
    }

    /**
     * Filas cuya URL apunta a Drive para la entidad de la carpeta.
     *
     * @return Collection<int, Model>
     */
    private function filasConUrlDrive(string $carpeta): Collection
    {
        if ($carpeta === 'perfiles') {
            return Usuario::query()->where('url_foto', 'like', '%drive.google.com%')->get();
        }

        return Foto::query()
            ->where('url_foto', 'like', '%drive.google.com%')
            ->whereNotNull(self::MAPA_FK[$carpeta])
            ->get();
    }

    private function contarLocales(string $carpeta): int
    {
        if ($carpeta === 'perfiles') {
            return Usuario::query()
                ->whereNotNull('url_foto')
                ->where('url_foto', 'not like', '%drive.google.com%')
                ->count();
        }

        return Foto::query()
            ->whereNotNull('url_foto')
            ->where('url_foto', 'not like', '%drive.google.com%')
            ->whereNotNull(self::MAPA_FK[$carpeta])
            ->count();
    }

    private function humano(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
