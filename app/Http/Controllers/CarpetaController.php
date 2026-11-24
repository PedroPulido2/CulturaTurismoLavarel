<?php

namespace App\Http\Controllers;

use App\Services\ArchivoStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;

class CarpetaController extends Controller
{
    public function __construct(private ArchivoStorageService $archivos) {}

    /**
     * Lista subcarpetas y archivos de un nivel (?padre= ruta, '' = raíz).
     */
    public function index(Request $request)
    {
        $padrePedido = trim((string) $request->query('padre', $request->query('carpeta', '')));

        try {
            $padreNorm = $this->archivos->normalizarCarpeta($padrePedido);
            $carpetas = $this->archivos->listarCarpetas($padreNorm);
            $archivos = $this->archivos->listar($padreNorm);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'padre' => $padreNorm,
                'carpetas' => $carpetas,
                'archivos' => $archivos,
            ],
        ]);
    }

    /**
     * Crea una carpeta {nombre, padre?}. Crea padres intermedios si faltan.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:60',
            'padre' => 'nullable|string|max:500',
        ], [
            'nombre.required' => 'Debe enviar el nombre de la carpeta.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $ruta = $this->archivos->crearCarpeta(
                (string) $request->input('nombre', ''),
                (string) $request->input('padre', '')
            );
        } catch (InvalidArgumentException $e) {
            $codigo = str_contains($e->getMessage(), 'ya existe') ? 409 : 422;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $codigo);
        }

        return response()->json([
            'success' => true,
            'data' => ['ruta' => $ruta, 'nombre' => basename(str_replace('\\', '/', $ruta))],
        ], 201);
    }

    /**
     * Renombra el último segmento de la ruta {nuevo_nombre}.
     */
    public function update(Request $request, string $ruta)
    {
        $validator = Validator::make($request->all(), [
            'nuevo_nombre' => 'required|string|max:60',
        ], [
            'nuevo_nombre.required' => 'Debe enviar el nuevo nombre de la carpeta.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $nuevaRuta = $this->archivos->renombrarCarpeta($ruta, (string) $request->input('nuevo_nombre', ''));
        } catch (InvalidArgumentException $e) {
            $mensaje = $e->getMessage();
            $codigo = str_contains($mensaje, 'no existe') ? 404 : (str_contains($mensaje, 'Ya existe') ? 409 : 422);

            return response()->json([
                'success' => false,
                'message' => $mensaje,
            ], $codigo);
        }

        return response()->json([
            'success' => true,
            'data' => ['ruta' => $nuevaRuta, 'nombre' => basename(str_replace('\\', '/', $nuevaRuta))],
        ]);
    }

    /**
     * Borra una carpeta solo si está vacía.
     */
    public function destroy(string $ruta)
    {
        try {
            $ok = $this->archivos->eliminarCarpeta($ruta);
        } catch (InvalidArgumentException $e) {
            $codigo = str_contains($e->getMessage(), 'No se puede eliminar la raíz') ? 422 : 422;

            // Distinguir "no existe" (mensaje contiene "no existe" solo en renombrar;
            // aquí eliminarCarpeta retorna false si no existe, no lanza).
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $codigo);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }

        if (! $ok) {
            return response()->json([
                'success' => false,
                'message' => 'Carpeta no encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Carpeta eliminada',
        ]);
    }
}
