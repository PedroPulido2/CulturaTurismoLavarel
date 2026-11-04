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
     * Fotos, documentos y video. 100 MB en KB para cubrir videos cortos.
     */
    private const MIMES = 'jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,mp4,mov,avi';

    private const MAX_KB = 102400;

    public function __construct(private ArchivoStorageService $archivos) {}

    /**
     * Lista privada de archivos (?search= subcadena, ?per_page= paginado).
     */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $search = mb_strtolower(trim((string) $request->query('search', '')));

        $todos = $this->archivos->listar();

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
            ],
        ]);
    }

    /**
     * Recibe el archivo del front (multipart/form-data, campo `archivo`).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'archivo' => 'required|file|mimes:'.self::MIMES.'|max:'.self::MAX_KB,
        ], [
            'archivo.required' => 'Debe enviar un archivo en el campo archivo.',
            'archivo.mimes' => 'Tipo no permitido. Use: '.self::MIMES.'.',
            'archivo.max' => 'El archivo supera el máximo de 100 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            /** @var UploadedFile $subido */
            $subido = $request->file('archivo');
            $nombre = $this->archivos->guardar($subido);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $detalle = null;
        foreach ($this->archivos->listar() as $f) {
            if ($f['nombre'] === $nombre) {
                $detalle = $f;
                break;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $detalle ?? ['nombre' => $nombre],
        ], 201);
    }

    /**
     * Descarga privada del archivo.
     */
    public function descargar(string $nombre)
    {
        $ruta = $this->archivos->rutaAbsoluta($nombre);

        if ($ruta === null) {
            return response()->json([
                'success' => false,
                'message' => 'Archivo no encontrado',
            ], 404);
        }

        return response()->download($ruta, $nombre);
    }

    /**
     * Elimina un archivo del gestor.
     */
    public function destroy(string $nombre)
    {
        if (! $this->archivos->eliminar($nombre)) {
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
