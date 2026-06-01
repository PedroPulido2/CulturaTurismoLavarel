<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AtractivoTuristico;
use App\Models\DireccionGoogle;
use App\Models\FotosAtractivo;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class AtractivoTuristicoController extends Controller
{
    protected $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    public function getAllAtractivos()
    {
        // todos los atractivos con su dirección y sus fotos
        $atractivos = AtractivoTuristico::with(['direccion', 'fotos'])->get();
        return response()->json(['success' => true, 'data' => $atractivos]);
    }

    public function getAtractivoById($id)
    {
        $atractivo = AtractivoTuristico::with(['direccion', 'fotos'])->find($id);

        if (!$atractivo) {
            return response()->json(['success' => false, 'message' => 'Atractivo turístico no encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => $atractivo]);
    }

    public function createAtractivo(Request $request)
    {
        //Validacion de datos incluyendo el array de fotos
        $validador = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|string|max:255',
            'direccion' => 'required|string', // Para tabla DireccionGoogle
            // latitud, longitud y place_id pueden ser opcionales dependiendo de tu front
            'fotos' => 'array', // Esperamos un arreglo de imágenes
            'fotos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120' // Máximo 5MB por foto
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            //Crear la direccion primero para obtener el id_direccion
            $direccion = DireccionGoogle::create([
                'direccion' => $request->direccion,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'google_place_id' => $request->google_place_id,
            ]);


            //Crear el atractivo turistico amarrado a la direccion
            $atractivo = AtractivoTuristico::create([
                'nombre' => $request->nombre,
                'tipo' => $request->tipo,
                'descripcion' => $request->descripcion,
                'telefono' => $request->telefono,
                'instagram' => $request->instagram,
                'facebook' => $request->facebook,
                'whatsapp' => $request->whatsapp,
                'web' => $request->web,
                'horario' => $request->horario,
                'precio' => $request->precio,
                'id_direccion' => $direccion->id_direccion
            ]);

            //Procesar y subir multiples fotos
            if ($request->hasFile('fotos')) {
                $idCarpetaDestino = env('ID_CARPETA_FOTOS_ATRACTIVOS');
                $archivos = $request->file('fotos');

                foreach ($archivos as $index => $archivo) {
                    //nombre unico nombre ID_ATRACTIVO.extension
                    $nombreArchivo = $atractivo->id_atractivo_turistico . '_' . ($index + 1) . '.' . $archivo->getClientOriginalExtension();

                    //sube al drive usando el service
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    //Guarda el registro en la base de datos
                    FotosAtractivo::create([
                        'url_foto' => $rutaFoto,
                        'id_atractivo_turistico' => $atractivo->id_atractivo_turistico,
                    ]);
                }
            }

            //Si todo salio bien se confirma la transaccion
            DB::commit();

            //Cargan las relaciones para devolver el objeto completo en la respuesta
            $atractivo->load(['direccion', 'fotos']);

            return response()->json([
                'success' => true,
                'message' => 'Atractivo turistico y ubicacion guardados exitosamente',
                'data' => $atractivo
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el atractivo turístico: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateAtractivo(Request $request, $id)
    {
        $atractivo = AtractivoTuristico::with(['direccion', 'fotos'])->find($id);

        if (!$atractivo) {
            return response()->json(['success' => false, 'message' => 'Atractivo turístico no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255',
            'tipo' => 'sometimes|string|max:255',
            'direccion' => 'sometimes|string',
            'nuevas_fotos' => 'sometimes|array',
            'nuevas_fotos.*' => 'image|mimes:jpeg,png,jpg|max:5120',
            'fotos_a_eliminar' => 'sometimes|array', // Array con los IDs de las fotos a borrar
            'fotos_a_eliminar.*' => 'integer'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            //Actualizar la Dirección si se enviaron datos
            if ($request->hasAny(['direccion', 'latitud', 'longitud', 'google_place_id'])) {
                $atractivo->direccion->update($request->only([
                    'direccion',
                    'latitud',
                    'longitud',
                    'google_place_id'
                ]));
            }

            //Actualizar los datos del Atractivo Turístico
            $atractivo->update($request->only([
                'nombre',
                'tipo',
                'descripcion',
                'telefono',
                'instagram',
                'facebook',
                'whatsapp',
                'web',
                'horario',
                'precio'
            ]));

            //Eliminar fotos específicas (de Drive y de la DB)
            if ($request->has('fotos_a_eliminar')) {
                $fotosAEliminar = FotosAtractivo::whereIn('id_foto', $request->fotos_a_eliminar)
                    ->where('id_atractivo_turistico', $id)
                    ->get();

                foreach ($fotosAEliminar as $foto) {
                    if ($foto->url_foto) {
                        $this->driveService->deleteFromDrive($foto->url_foto);
                    }
                    $foto->delete(); // La borramos de la base de datos
                }
            }

            //Subir y guardar las nuevas fotos
            if ($request->hasFile('nuevas_fotos')) {
                $idCarpetaDestino = env('ID_CARPETA_FOTOS_ATRACTIVOS');
                $archivos = $request->file('nuevas_fotos');

                foreach ($archivos as $index => $archivo) {
                    // Se utiliza time() en el nombre para evitar sobreescribir archivos 
                    $nombreArchivo = $id . '_' . time() . '_' . $index . '.' . $archivo->getClientOriginalExtension();

                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    FotosAtractivo::create([
                        'url_foto' => $rutaFoto,
                        'id_atractivo_turistico' => $id
                    ]);
                }
            }

            DB::commit();

            // Recargan las relaciones para devolver la información actualizada
            $atractivo->load(['direccion', 'fotos']);

            return response()->json([
                'success' => true,
                'message' => 'Atractivo actualizado correctamente',
                'data' => $atractivo
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }


    public function updateVisibility(Request $request, $id)
    {
        $atractivo = AtractivoTuristico::find($id);

        if (!$atractivo) {
            return response()->json(['success' => false, 'message' => 'Atractivo no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'isvisible' => 'required|boolean'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            // Se actualiza únicamente el campo de visibilidad
            $atractivo->isvisible = $request->isvisible;
            $atractivo->save();

            return response()->json([
                'success' => true,
                'message' => 'Visibilidad actualizada correctamente',
                'data' => [
                    'id_atractivo_turistico' => $atractivo->id_atractivo_turistico, 
                    'isvisible' => $atractivo->isvisible
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar visibilidad: ' . $e->getMessage()
            ], 500);
        }
    }


    public function deleteAtractivo($id)
    {
        $atractivo = AtractivoTuristico::with('fotos')->find($id);

        if (!$atractivo) {
            return response()->json(['success' => false, 'message' => 'Atractivo no encontrado'], 404);
        }

        try {
            DB::beginTransaction();

            foreach ($atractivo->fotos as $foto) {
                if ($foto->url_foto) {
                    $this->driveService->deleteFromDrive($foto->url_foto);
                }
            }
            $atractivo->fotos()->delete();
            $idDireccion = $atractivo->id_direccion;
            $atractivo->delete();

            if ($idDireccion) {
                DireccionGoogle::where('id_direccion', $idDireccion)->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Atractivo, dirección e imágenes eliminados correctamente en su totalidad.'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }
}
