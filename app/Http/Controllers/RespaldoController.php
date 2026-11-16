<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class RespaldoController extends Controller
{
    /**
     * Inventario de fotos locales (storage/app/public/fotos/).
     * Solo Super Administrador. Lectura rápida para verificar antes de descargar.
     */
    public function inventario(): JsonResponse
    {
        $archivos = $this->archivosFotos();

        $porCarpeta = [];
        $bytes = 0;

        foreach ($archivos as $relativa) {
            $partes = explode('/', $relativa);
            $carpeta = $partes[1] ?? 'raiz';
            $porCarpeta[$carpeta] = ($porCarpeta[$carpeta] ?? 0) + 1;
            try {
                $bytes += Storage::disk('public')->size($relativa);
            } catch (\Throwable) {
                // Si un archivo desaparece a mitad del listado, se ignora.
            }
        }

        ksort($porCarpeta);

        return response()->json([
            'success' => true,
            'data' => [
                'total' => count($archivos),
                'size_bytes' => $bytes,
                'por_carpeta' => $porCarpeta,
            ],
        ]);
    }

    /**
     * Descarga ZIP con todas las fotos locales preservando la estructura
     * fotos/{carpeta}/... para copiar tal cual al deploy final.
     * Solo Super Administrador.
     */
    public function descargar(): BinaryFileResponse|JsonResponse
    {
        if (! class_exists(ZipArchive::class)) {
            return response()->json([
                'success' => false,
                'message' => 'Respaldo no disponible: extensión ZIP no instalada en el servidor',
            ], 500);
        }

        $archivos = $this->archivosFotos();

        if ($archivos === []) {
            return response()->json([
                'success' => false,
                'message' => 'No hay fotos locales para respaldar',
            ], 404);
        }

        $temporal = rtrim(sys_get_temp_dir(), '/').'/fotos-'.now()->format('Ymd-His').'-'.uniqid().'.zip';

        $zip = new ZipArchive;
        if ($zip->open($temporal, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar el archivo de respaldo',
            ], 500);
        }

        $omitidos = 0;

        foreach ($archivos as $relativa) {
            try {
                $absoluta = Storage::disk('public')->path($relativa);
            } catch (\Throwable) {
                $omitidos++;

                continue;
            }

            if (! is_file($absoluta) || ! is_readable($absoluta)) {
                $omitidos++;

                continue;
            }

            if (! $zip->addFile($absoluta, $relativa)) {
                $omitidos++;
            }
        }

        $zip->close();

        if (! is_file($temporal)) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar el archivo de respaldo',
            ], 500);
        }

        return response()->download(
            $temporal,
            'fotos-'.now()->format('Ymd-His').'.zip',
            ['Content-Type' => 'application/zip']
        )->deleteFileAfterSend(true);
    }

    /**
     * Rutas relativas bajo fotos/, sin .gitignore/.gitkeep.
     *
     * @return list<string>
     */
    private function archivosFotos(): array
    {
        try {
            if (! Storage::disk('public')->exists('fotos')) {
                return [];
            }

            $todos = Storage::disk('public')->allFiles('fotos');
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
}
