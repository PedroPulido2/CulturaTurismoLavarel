<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ArchivoStorageService
{
    /**
     * Raíz global dentro del disco privado local
     * (storage/app/private/archivos/). No es pública: la descarga
     * se sirve solo vía controlador con auth.jwt.
     * Dentro puede haber carpetas anidadas: archivos/a/b/...
     * La carpeta vacía ('') representa la raíz para compatibilidad.
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
     * Normaliza una ruta de carpeta relativa a la raíz.
     * '' = raíz. 'a//b/' => 'a/b'. Lanza InvalidArgumentException si hay
     * traversal o segmentos inválidos.
     */
    public function normalizarCarpeta(?string $carpeta): string
    {
        $carpeta = trim((string) $carpeta);
        $carpeta = str_replace('\\', '/', $carpeta);
        $carpeta = trim($carpeta, " \t\n\r\0\x0B/");

        if ($carpeta === '' || $carpeta === '.') {
            return '';
        }

        $partes = array_values(array_filter(
            explode('/', $carpeta),
            fn (string $p) => $p !== '' && $p !== '.'
        ));

        if ($partes === []) {
            return '';
        }

        foreach ($partes as $segmento) {
            if (! $this->segmentoValido($segmento)) {
                throw new InvalidArgumentException("Nombre de carpeta no válido: {$segmento}. Use minúsculas, números, guion y guion bajo.");
            }
        }

        return implode('/', array_map(fn (string $p) => strtolower($p), $partes));
    }

    /**
     * Un segmento: empieza con alfanumérico, resto [a-z0-9-_], máx 60.
     */
    public function segmentoValido(string $segmento): bool
    {
        $segmento = strtolower(trim($segmento));

        if ($segmento === '' || $segmento === '.' || $segmento === '..') {
            return false;
        }

        if (str_contains($segmento, '/') || str_contains($segmento, '\\')) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9][a-z0-9\-_]{0,59}$/', $segmento);
    }

    /**
     * Prefijo en disco para una carpeta normalizada ('' => 'archivos').
     */
    public function prefijoCarpeta(string $carpetaNormalizada): string
    {
        return $carpetaNormalizada === ''
            ? self::CARPETA
            : self::CARPETA.'/'.$carpetaNormalizada;
    }

    /**
     * Lista subcarpetas inmediatas de una carpeta (?padre).
     *
     * @return array<int, array{nombre: string, ruta: string}>
     */
    public function listarCarpetas(?string $padre = null): array
    {
        $padreNorm = $this->normalizarCarpeta($padre);
        $base = $this->prefijoCarpeta($padreNorm);

        $dirs = Storage::disk('local')->exists($base)
            ? Storage::disk('local')->directories($base)
            : [];

        $lista = [];

        foreach ($dirs as $dir) {
            $nombre = basename($dir);

            if (! $this->segmentoValido($nombre)) {
                continue;
            }

            $ruta = $padreNorm === '' ? strtolower($nombre) : $padreNorm.'/'.strtolower($nombre);
            $lista[] = ['nombre' => strtolower($nombre), 'ruta' => $ruta];
        }

        usort($lista, fn (array $a, array $b) => strcmp($a['ruta'], $b['ruta']));

        return $lista;
    }

    /**
     * Crea una carpeta (y padres intermedios si faltan). Retorna la ruta normalizada.
     */
    public function crearCarpeta(string $nombre, ?string $padre = null): string
    {
        $nombre = strtolower(trim($nombre));

        if (! $this->segmentoValido($nombre)) {
            throw new InvalidArgumentException("Nombre de carpeta no válido: {$nombre}. Use minúsculas, números, guion y guion bajo.");
        }

        $padreNorm = $this->normalizarCarpeta($padre);
        $ruta = $padreNorm === '' ? $nombre : $padreNorm.'/'.$nombre;

        if (strlen($ruta) > 500) {
            throw new InvalidArgumentException('La ruta de la carpeta es demasiado larga.');
        }

        $destino = $this->prefijoCarpeta($ruta);

        if (Storage::disk('local')->exists($destino)) {
            throw new InvalidArgumentException("La carpeta ya existe: {$ruta}.");
        }

        Storage::disk('local')->makeDirectory($destino);

        return $ruta;
    }

    /**
     * Renombra solo el último segmento de la ruta. Retorna la nueva ruta.
     */
    public function renombrarCarpeta(string $ruta, string $nuevoNombre): string
    {
        $rutaNorm = $this->normalizarCarpeta($ruta);

        if ($rutaNorm === '') {
            throw new InvalidArgumentException('No se puede renombrar la raíz.');
        }

        $nuevoNombre = strtolower(trim($nuevoNombre));

        if (! $this->segmentoValido($nuevoNombre)) {
            throw new InvalidArgumentException("Nombre de carpeta no válido: {$nuevoNombre}.");
        }

        $origen = $this->prefijoCarpeta($rutaNorm);

        if (! Storage::disk('local')->exists($origen)) {
            throw new InvalidArgumentException("La carpeta no existe: {$rutaNorm}.");
        }

        $pos = strrpos($rutaNorm, '/');
        $padre = $pos === false ? '' : substr($rutaNorm, 0, $pos);
        $nuevaRuta = $padre === '' ? $nuevoNombre : $padre.'/'.$nuevoNombre;

        if ($nuevaRuta === $rutaNorm) {
            return $rutaNorm;
        }

        $destino = $this->prefijoCarpeta($nuevaRuta);

        if (Storage::disk('local')->exists($destino)) {
            throw new InvalidArgumentException("Ya existe una carpeta en: {$nuevaRuta}.");
        }

        if (! Storage::disk('local')->move($origen, $destino)) {
            throw new InvalidArgumentException("No se pudo renombrar la carpeta: {$rutaNorm}.");
        }

        return $nuevaRuta;
    }

    /**
     * Elimina una carpeta solo si está vacía. True si se borró.
     * Lanza InvalidArgumentException (404 equivalente) si no existe
     * y RuntimeException si no está vacía.
     */
    public function eliminarCarpeta(string $ruta): bool
    {
        $rutaNorm = $this->normalizarCarpeta($ruta);

        if ($rutaNorm === '') {
            throw new InvalidArgumentException('No se puede eliminar la raíz.');
        }

        $objetivo = $this->prefijoCarpeta($rutaNorm);

        if (! Storage::disk('local')->exists($objetivo)) {
            return false;
        }

        $archivos = Storage::disk('local')->files($objetivo);
        $subcarpetas = Storage::disk('local')->directories($objetivo);

        if ($archivos !== [] || $subcarpetas !== []) {
            throw new \RuntimeException("La carpeta no está vacía: {$rutaNorm}. Vacíela primero.");
        }

        return Storage::disk('local')->deleteDirectory($objetivo);
    }

    /**
     * Guarda un archivo subido y retorna el nombre generado.
     */
    public function guardar(UploadedFile $archivo, ?string $carpeta = null, ?string $base = null): string
    {
        $carpetaNorm = $this->normalizarCarpeta($carpeta);
        $destino = $this->prefijoCarpeta($carpetaNorm);

        $extension = strtolower(ltrim($archivo->getClientOriginalExtension() ?: '', '.'));

        if (! in_array($extension, self::EXTENSIONES, true)) {
            throw new InvalidArgumentException("Extensión no permitida: {$extension}");
        }

        if (! Storage::disk('local')->exists($destino)) {
            Storage::disk('local')->makeDirectory($destino);
        }

        $nombre = Str::slug(pathinfo($base ?? $archivo->getClientOriginalName(), PATHINFO_FILENAME) ?: 'archivo')
            .'_'.(string) Str::uuid().'.'.$extension;

        Storage::disk('local')->putFileAs($destino, $archivo, $nombre);

        return $nombre;
    }

    /**
     * Lista los archivos guardados en una carpeta con su metadata básica.
     *
     * @return array<int, array{nombre: string, carpeta: string, ruta: string, size_bytes: int, mime: ?string, modificado: int}>
     */
    public function listar(?string $carpeta = null): array
    {
        $carpetaNorm = $this->normalizarCarpeta($carpeta);
        $base = $this->prefijoCarpeta($carpetaNorm);

        $rutas = Storage::disk('local')->exists($base)
            ? Storage::disk('local')->files($base)
            : [];

        $lista = [];

        foreach ($rutas as $ruta) {
            $nombre = basename($ruta);

            if (! $this->nombreValido($nombre)) {
                continue;
            }

            $lista[] = [
                'nombre' => $nombre,
                'carpeta' => $carpetaNorm,
                'ruta' => $carpetaNorm === '' ? $nombre : $carpetaNorm.'/'.$nombre,
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
    public function rutaAbsoluta(string $nombre, ?string $carpeta = null): ?string
    {
        if (! $this->nombreValido($nombre)) {
            return null;
        }

        try {
            $carpetaNorm = $this->normalizarCarpeta($carpeta);
        } catch (InvalidArgumentException) {
            return null;
        }

        $relativa = $this->prefijoCarpeta($carpetaNorm).'/'.$nombre;

        if (! Storage::disk('local')->exists($relativa)) {
            return null;
        }

        return Storage::disk('local')->path($relativa);
    }

    /**
     * Elimina un archivo. Retorna true si existía y se borró.
     */
    public function eliminar(string $nombre, ?string $carpeta = null): bool
    {
        if (! $this->nombreValido($nombre)) {
            return false;
        }

        try {
            $carpetaNorm = $this->normalizarCarpeta($carpeta);
        } catch (InvalidArgumentException) {
            return false;
        }

        $relativa = $this->prefijoCarpeta($carpetaNorm).'/'.$nombre;

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
