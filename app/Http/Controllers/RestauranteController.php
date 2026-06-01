<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Restaurante;
use App\Models\DireccionGoogle;
use App\Models\FotosRestaurante;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class RestauranteController extends Controller
{
    protected $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    public function getAllRestaurantes()
    {
        $restaurantes = Restaurante::with(['direccion', 'fotos'])->get();
        return response()->json(['success' => true, 'data' => $restaurantes]);
    }

    public function getRestauranteById($id)
    {
        $restaurante = Restaurante::with(['direccion', 'fotos'])->find($id);

        if (!$restaurante) {
            return response()->json(['success' => false, 'message' => 'Restaurante no encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => $restaurante]);
    }

    public function createRestaurante(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string',
            'correo' => 'nullable|email|max:255',
            'fotos' => 'sometimes|array',
            'fotos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            $direccion = DireccionGoogle::create([
                'direccion' => $request->direccion,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'google_place_id' => $request->google_place_id
            ]);

            $restaurante = Restaurante::create([
                'nombre' => $request->nombre,
                'celular' => $request->celular,
                'correo' => $request->correo,
                'tipo_cocina' => $request->tipo_cocina,
                'horarios' => $request->horarios,
                'propietario' => $request->propietario,
                'capacidad' => $request->capacidad,
                'instagram' => $request->instagram,
                'facebook' => $request->facebook,
                'whatsapp' => $request->whatsapp,
                'web' => $request->web,
                'platos_principales' => $request->platos_principales,
                'id_direccion' => $direccion->id_direccion
            ]);

            if ($request->hasFile('fotos')) {
                $idCarpetaDestino = env('ID_CARPETA_FOTOS_RESTAURANTES');
                $archivos = $request->file('fotos');

                foreach ($archivos as $index => $archivo) {
                    $nombreArchivo = 'restaurante_' . $restaurante->id_restaurante . '_' . time() . '_' . $index . '.' . $archivo->getClientOriginalExtension();
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    FotosRestaurante::create([
                        'url_foto' => $rutaFoto,
                        'id_restaurante' => $restaurante->id_restaurante
                    ]);
                }
            }

            DB::commit();
            $restaurante->load(['direccion', 'fotos']);

            return response()->json([
                'success' => true,
                'message' => 'Restaurante registrado exitosamente',
                'data' => $restaurante
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el restaurante: ' . $e->getMessage()
            ], 500);
        }
    }

        public function updateVisibility(Request $request, $id)
    {
        $restaurante = Restaurante::find($id);

        if (!$restaurante) {
            return response()->json(['success' => false, 'message' => 'restaurante no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'isvisible' => 'required|boolean'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            // Se actualiza únicamente el campo de visibilidad
            $restaurante->isvisible = $request->isvisible;
            $restaurante->save();

            return response()->json([
                'success' => true,
                'message' => 'Visibilidad actualizada correctamente',
                'data' => [
                    'id_restaurante' => $restaurante->id_restaurante, 
                    'isvisible' => $restaurante->isvisible
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar visibilidad: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateRestaurante(Request $request, $id)
    {
        $restaurante = Restaurante::with(['direccion', 'fotos'])->find($id);

        if (!$restaurante) {
            return response()->json(['success' => false, 'message' => 'Restaurante no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255',
            'correo' => 'nullable|email|max:255',
            'nuevas_fotos' => 'sometimes|array',
            'nuevas_fotos.*' => 'image|mimes:jpeg,png,jpg|max:5120',
            'fotos_a_eliminar' => 'sometimes|array',
            'fotos_a_eliminar.*' => 'integer'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            if ($request->hasAny(['direccion', 'latitud', 'longitud', 'google_place_id'])) {
                $restaurante->direccion->update($request->only([
                    'direccion',
                    'latitud',
                    'longitud',
                    'google_place_id'
                ]));
            }

            $restaurante->update($request->only([
                'nombre',
                'celular',
                'correo',
                'tipo_cocina',
                'horarios',
                'propietario',
                'capacidad',
                'instagram',
                'facebook',
                'whatsapp',
                'web',
                'platos_principales'
            ]));

            if ($request->has('fotos_a_eliminar')) {
                $fotosAEliminar = FotosRestaurante::whereIn('id_foto', $request->fotos_a_eliminar)
                    ->where('id_restaurante', $id)
                    ->get();

                foreach ($fotosAEliminar as $foto) {
                    if ($foto->url_foto) {
                        $this->driveService->deleteFromDrive($foto->url_foto);
                    }
                    $foto->delete();
                }
            }

            if ($request->hasFile('nuevas_fotos')) {
                $idCarpetaDestino = env('ID_CARPETA_FOTOS_RESTAURANTES');
                $archivos = $request->file('nuevas_fotos');

                foreach ($archivos as $index => $archivo) {
                    $nombreArchivo = 'restaurante_' . $id . '_' . time() . '_' . $index . '.' . $archivo->getClientOriginalExtension();
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    FotosRestaurante::create([
                        'url_foto' => $rutaFoto,
                        'id_restaurante' => $id
                    ]);
                }
            }

            DB::commit();
            $restaurante->load(['direccion', 'fotos']);

            return response()->json([
                'success' => true,
                'message' => 'Restaurante actualizado correctamente',
                'data' => $restaurante
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el restaurante: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteRestaurante($id)
    {
        $restaurante = Restaurante::with('fotos')->find($id);

        if (!$restaurante) {
            return response()->json(['success' => false, 'message' => 'Restaurante no encontrado'], 404);
        }

        try {
            DB::beginTransaction();

            foreach ($restaurante->fotos as $foto) {
                if ($foto->url_foto) {
                    $this->driveService->deleteFromDrive($foto->url_foto);
                }
            }

            $idDireccion = $restaurante->id_direccion;

            $restaurante->delete();
            DireccionGoogle::where('id_direccion', $idDireccion)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Restaurante y sus imágenes eliminados correctamente'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el restaurante: ' . $e->getMessage()
            ], 500);
        }
    }
}
