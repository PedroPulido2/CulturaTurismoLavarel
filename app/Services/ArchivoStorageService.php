<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ArchivoStorageService
{
    /**
     * Carpeta única global dentro del disco privado local
     * (storage/app/private/archivos/). No es pública: la descarga
     * se sirve solo vía controlador con auth.jwt.
     */
    public const CARPETA = 'archivos';

    /**
     * Extensiones permitidas: fotos, documentos y video.
     */
    public const EXTENSIONES = [
        'jpg', 'jpeg', 'png', 'webp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx',
        'mp4', 'mov', 'avi',
    ];

    /**
     * Guarda un archivo subido y retorna el nombre generado.
     */
    public function guardar(UploadedFile $archivo, ?string $base = null): string
    {
        $extension = strtolower(ltrim($archivo->getClientOriginalExtension() ?: '', '.'));

        if (! in_array($extension, self::EXTENSIONES, true)) {
            throw new InvalidArgumentException("Extensión no permitida: {$extension}");
        }

        $nombre = Str::slug(pathinfo($base ?? $archivo->getClientOriginalName(), PATHINFO_FILENAME) ?: 'archivo')
            .'_'.(string) Str::uuid().'.'.$extension;

        Storage::disk('local')->putFileAs(self::CARPETA, $archivo, $nombre);

        return $nombre;
    }

    /**
     * Lista los archivos guardados con su metadata básica.
     *
     * @return array<int, array{nombre: string, size_bytes: int, mime: ?string, modificado: int}>
     */
    public function listar(): array
    {
        $rutas = Storage::disk('local')->exists(self::CARPETA)
            ? Storage::disk('local')->files(self::CARPETA)
            : [];

        $lista = [];

        foreach ($rutas as $ruta) {
            $nombre = basename($ruta);

            if (! $this->nombreValido($nombre)) {
                continue;
            }

            $lista[] = [
                'nombre' => $nombre,
                'size_bytes' => Storage::disk('local')->size($ruta),
                'mime' => Storage::disk('local')->mimeType($ruta) ?: null,
                'modificado' => Storage::disk('local')->lastModified($ruta),
            ];
        }

        usort($lista, fn (array $a, array $b) => strcmp($a['nombre'], $b['nombre']));

        return $lista;
    }

    /**
     * Ruta absoluta del archivo si existe y el nombre es válido.
     */
    public function rutaAbsoluta(string $nombre): ?string
    {
        if (! $this->nombreValido($nombre)) {
            return null;
        }

        $relativa = self::CARPETA.'/'.$nombre;

        if (! Storage::disk('local')->exists($relativa)) {
            return null;
        }

        return Storage::disk('local')->path($relativa);
    }

    /**
     * Elimina un archivo. Retorna true si existía y se borró.
     */
    public function eliminar(string $nombre): bool
    {
        if (! $this->nombreValido($nombre)) {
            return false;
        }

        $relativa = self::CARPETA.'/'.$nombre;

        if (! Storage::disk('local')->exists($relativa)) {
            return false;
        }

        return Storage::disk('local')->delete($relativa);
    }

    /**
     * Solo basenames sin traversal y con extensión permitida.
     */
    public function nombreValido(string $nombre): bool
    {
        if ($nombre === '' || $nombre !== basename($nombre)) {
            return false;
        }

        if (str_contains($nombre, '..')) {
            return false;
        }

        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

        return in_array($extension, self::EXTENSIONES, true);
    }
}
