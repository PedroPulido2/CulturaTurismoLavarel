<?php

namespace App\Http\Controllers;

use App\Services\ArchivoStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class ArchivoController extends Controller
{
    /**
     * Fotos, documentos y video. 2 GB en KB para videos en buena resolución.
     * Debe ir acorde al php.ini de producción (upload 2050M / post 2070M).
     */
    private const MIMES = 'jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,mp4,mov,avi';

    private const MAX_KB = 2097152;

    public function __construct(private ArchivoStorageService $archivos) {}

    /**
     * Lista privada de archivos (?carpeta= ruta, ?search= subcadena, ?per_page= paginado).
     * Sin carpeta lista la raíz para compatibilidad.
     */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $carpetaPedida = trim((string) $request->query('carpeta', ''));

        try {
            $todos = $this->archivos->listar($carpetaPedida);
            $carpetaNorm = $this->archivos->normalizarCarpeta($carpetaPedida);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($search !== '') {
            $todos = array_values(array_filter(
                $todos,
                fn (array $f) => str_contains(mb_strtolower($f['nombre']), $search)
            ));
        }

        $total = count($todos);
        $pagina = max((int) $request->query('page', 1), 1);
        $datos = array_slice($todos, ($pagina - 1) * $perPage, $perPage);

        return response()->json([
            'success' => true,
            'data' => $datos,
            'meta' => [
                'current_page' => $pagina,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
                'carpeta' => $carpetaNorm,
            ],
        ]);
    }

    /**
     * Recibe el archivo del front (multipart/form-data, campos `archivo` y `carpeta?`).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'archivo' => 'required|file|mimes:'.self::MIMES.'|max:'.self::MAX_KB,
            'carpeta' => 'nullable|string|max:500',
        ], [
            'archivo.required' => 'Debe enviar un archivo en el campo archivo.',
            'archivo.mimes' => 'Tipo no permitido. Use: '.self::MIMES.'.',
            'archivo.max' => 'El archivo supera el máximo de 2 GB.',
            'archivo.uploaded' => 'El archivo no pudo subirse (supera el límite del servidor o la conexión se cortó). Máximo 2 GB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $carpetaPedida = trim((string) $request->input('carpeta', ''));

        try {
            /** @var UploadedFile $subido */
            $subido = $request->file('archivo');
            $nombre = $this->archivos->guardar($subido, $carpetaPedida);
            $carpetaNorm = $this->archivos->normalizarCarpeta($carpetaPedida);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $detalle = null;
        foreach ($this->archivos->listar($carpetaNorm) as $f) {
            if ($f['nombre'] === $nombre) {
                $detalle = $f;
                break;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $detalle ?? ['nombre' => $nombre, 'carpeta' => $carpetaNorm],
        ], 201);
    }

    /**
     * Descarga privada del archivo (?carpeta= para subcarpetas, raíz si se omite).
     */
    public function descargar(Request $request, string $nombre)
    {
        $carpetaPedida = trim((string) $request->query('carpeta', ''));
        $ruta = $this->archivos->rutaAbsoluta($nombre, $carpetaPedida);

        if ($ruta === null) {
            return response()->json([
                'success' => false,
                'message' => 'Archivo no encontrado',
            ], 404);
        }

        return response()->download($ruta, $nombre);
    }

    /**
     * Elimina un archivo del gestor (?carpeta= para subcarpetas).
     */
    public function destroy(Request $request, string $nombre)
    {
        $carpetaPedida = trim((string) $request->query('carpeta', ''));

        if (! $this->archivos->eliminar($nombre, $carpetaPedida)) {
            return response()->json([
                'success' => false,
                'message' => 'Archivo no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Archivo eliminado',
        ]);
    }
}
