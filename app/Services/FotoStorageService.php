<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FotoStorageService
{
    /**
     * Carpetas permitidas bajo storage/app/public/fotos/.
     * Cada entidad guarda sus fotos en su propia carpeta.
     */
    public const CARPETAS = [
        'hoteles',
        'atractivos',
        'restaurantes',
        'agencias',
        'eventos',
        'servicios',
        'perfiles',
    ];

    /**
     * Guarda una foto subida en el disco público local y retorna su URL pública absoluta.
     */
    public function guardar(UploadedFile $archivo, string $carpeta, string $base): string
    {
        if (! in_array($carpeta, self::CARPETAS, true)) {
            throw new InvalidArgumentException("Carpeta de fotos no permitida: {$carpeta}");
        }

        $extension = strtolower(ltrim($archivo->getClientOriginalExtension() ?: 'jpg', '.')) ?: 'jpg';
        $nombre = Str::slug($base).'_'.(string) Str::uuid().'.'.$extension;
        $relativa = "fotos/{$carpeta}/{$nombre}";

        Storage::disk('public')->put($relativa, file_get_contents($archivo->getRealPath()));

        return $this->urlPublica($relativa);
    }

    /**
     * Elimina una foto local a partir de su URL. Acepta null, URLs
     * externas o rutas inexistentes sin lanzar excepciones.
     */
    public function eliminar(?string $url): void
    {
        $relativa = $this->relativaDesdeUrl($url);

        if ($relativa === null) {
            return;
        }

        try {
            if (Storage::disk('public')->exists($relativa)) {
                Storage::disk('public')->delete($relativa);
            }
        } catch (\Exception $e) {
            Log::error('Error eliminando foto local: '.$e->getMessage());
        }
    }

    /**
     * Convierte una URL pública local en su ruta relativa dentro del disco public.
     * Retorna null si no es una URL local válida (null, vacía, externa o con traversal).
     */
    private function relativaDesdeUrl(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $pos = strpos($url, '/storage/');
        if ($pos === false) {
            return null;
        }

        $relativa = substr($url, $pos + strlen('/storage/'));

        if ($relativa === '' || str_contains($relativa, '..') || ! str_starts_with($relativa, 'fotos/')) {
            return null;
        }

        return $relativa;
    }

    private function urlPublica(string $relativa): string
    {
        return rtrim(config('app.url', 'http://localhost'), '/').'/storage/'.$relativa;
    }
}
