<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SyncRedesSociales;
use App\Models\AtractivoTuristico;
use App\Models\DireccionGoogle;
use App\Models\Foto;
use App\Services\GoogleDriveService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AtractivoTuristicoController extends Controller
{
    use SyncRedesSociales;

    protected $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    public function getAllAtractivos()
    {
        // todos los atractivos con su dirección, fotos y redes sociales
        $atractivos = AtractivoTuristico::with(['direccion', 'fotos', 'redesSociales'])->get();

        return response()->json(['success' => true, 'data' => $atractivos]);
    }

    public function getAtractivoById($id)
    {
        $atractivo = AtractivoTuristico::with(['direccion', 'fotos', 'redesSociales'])->find($id);

        if (! $atractivo) {
            return response()->json(['success' => false, 'message' => 'Atractivo turístico no encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => $atractivo]);
    }

    public function createAtractivo(Request $request)
    {
        // Validacion de datos incluyendo el array de fotos y redes sociales
        $validador = Validator::make($request->all(), array_merge([
            'id_atractivo_turistico' => ['required', 'string', 'max:15', Rule::unique(AtractivoTuristico::class, 'id_atractivo_turistico')],
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'telefono' => 'nullable|integer',
            'horario' => 'nullable|string',
            'precio' => 'nullable|string',
            'is_visible' => 'nullable|boolean',
            'direccion' => 'required|string',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'fotos' => 'array', // Esperamos un arreglo de imágenes
            'fotos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120', // Máximo 5MB por foto
        ], $this->reglasRedesSociales()));

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            // Crear la direccion primero para obtener el id_direccion
            $direccion = DireccionGoogle::create([
                'direccion' => $request->direccion,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
            ]);

            // Crear el atractivo turistico amarrado a la direccion
            $atractivo = AtractivoTuristico::create([
                'id_atractivo_turistico' => $request->id_atractivo_turistico,
                'nombre' => $request->nombre,
                'tipo' => $request->tipo,
                'descripcion' => $request->descripcion,
                'telefono' => $request->telefono,
                'horario' => $request->horario,
                'precio' => $request->precio,
                'is_visible' => filter_var($request->is_visible, FILTER_VALIDATE_BOOLEAN),
                'id_direccion' => $direccion->id_direccion,
            ]);

            // Redes sociales del atractivo (pivote atractivo_redes_sociales)
            $this->syncRedesSociales(
                'culturayturismo.atractivo_redes_sociales',
                'id_atractivo_turistico',
                $atractivo->id_atractivo_turistico,
                $request->input('redes_sociales')
            );

            // Procesar y subir multiples fotos
            if ($request->hasFile('fotos')) {
                $idCarpetaDestino = env('ID_CARPETA_FOTOS_ATRACTIVOS');
                $archivos = $request->file('fotos');

                foreach ($archivos as $index => $archivo) {
                    // nombre unico ID_ATRACTIVO_n.extension
                    $nombreArchivo = $atractivo->id_atractivo_turistico.'_'.($index + 1).'.'.$archivo->getClientOriginalExtension();

                    // sube al drive usando el service
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    // Guarda el registro en la base de datos (la primera foto queda como portada)
                    Foto::create([
                        'url_foto' => $rutaFoto,
                        'is_portada' => $index === 0,
                        'id_atractivo_turistico' => $atractivo->id_atractivo_turistico,
                    ]);
                }
            }

            DB::commit();

            // Cargan las relaciones para devolver el objeto completo en la respuesta
            $atractivo->load(['direccion', 'fotos', 'redesSociales']);

            return response()->json([
                'success' => true,
                'message' => 'Atractivo turistico y ubicacion guardados exitosamente',
                'data' => $atractivo,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el atractivo turístico: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateAtractivo(Request $request, $id)
    {
        $atractivo = AtractivoTuristico::with(['direccion', 'fotos', 'redesSociales'])->find($id);

        if (! $atractivo) {
            return response()->json(['success' => false, 'message' => 'Atractivo turístico no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), array_merge([
            'nombre' => 'sometimes|string|max:255',
            'tipo' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'telefono' => 'nullable|integer',
            'horario' => 'nullable|string',
            'precio' => 'nullable|string',
            'is_visible' => 'nullable|boolean',
            'direccion' => 'sometimes|string',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'nuevas_fotos' => 'sometimes|array',
            'nuevas_fotos.*' => 'image|mimes:jpeg,png,jpg|max:5120',
            'fotos_a_eliminar' => 'sometimes|array', // Array con los IDs de las fotos a borrar
            'fotos_a_eliminar.*' => 'integer',
        ], $this->reglasRedesSociales()));

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            // Actualizar la Dirección si se enviaron datos
            if ($request->hasAny(['direccion', 'latitud', 'longitud'])) {
                $atractivo->direccion->update($request->only([
                    'direccion',
                    'latitud',
                    'longitud',
                ]));
            }

            // Actualizar los datos del Atractivo Turístico
            $datos = $request->only([
                'nombre',
                'tipo',
                'descripcion',
                'telefono',
                'horario',
                'precio',
            ]);

            if ($request->has('is_visible')) {
                $datos['is_visible'] = filter_var($request->is_visible, FILTER_VALIDATE_BOOLEAN);
            }

            $atractivo->update($datos);

            // Reemplazar redes sociales si se enviaron
            if ($request->has('redes_sociales')) {
                $this->syncRedesSociales(
                    'culturayturismo.atractivo_redes_sociales',
                    'id_atractivo_turistico',
                    $id,
                    $request->input('redes_sociales')
                );
            }

            // Eliminar fotos específicas (de Drive y de la DB)
            if ($request->has('fotos_a_eliminar')) {
                $fotosAEliminar = Foto::whereIn('id_foto', $request->fotos_a_eliminar)
                    ->where('id_atractivo_turistico', $id)
                    ->get();

                foreach ($fotosAEliminar as $foto) {
                    if ($foto->url_foto) {
                        $this->driveService->deleteFromDrive($foto->url_foto);
                    }
                    $foto->delete(); // La borramos de la base de datos
                }
            }

            // Subir y guardar las nuevas fotos
            if ($request->hasFile('nuevas_fotos')) {
                $idCarpetaDestino = env('ID_CARPETA_FOTOS_ATRACTIVOS');
                $archivos = $request->file('nuevas_fotos');

                foreach ($archivos as $index => $archivo) {
                    // Se utiliza time() en el nombre para evitar sobreescribir archivos
                    $nombreArchivo = $id.'_'.time().'_'.$index.'.'.$archivo->getClientOriginalExtension();

                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    Foto::create([
                        'url_foto' => $rutaFoto,
                        'is_portada' => false,
                        'id_atractivo_turistico' => $id,
                    ]);
                }
            }

            DB::commit();

            // Recargan las relaciones para devolver la información actualizada
            $atractivo->load(['direccion', 'fotos', 'redesSociales']);

            return response()->json([
                'success' => true,
                'message' => 'Atractivo actualizado correctamente',
                'data' => $atractivo,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateVisibility(Request $request, $id)
    {
        $atractivo = AtractivoTuristico::find($id);

        if (! $atractivo) {
            return response()->json(['success' => false, 'message' => 'Atractivo no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'is_visible' => 'required|boolean',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            // Se actualiza únicamente el campo de visibilidad
            $atractivo->is_visible = $request->is_visible;
            $atractivo->save();

            return response()->json([
                'success' => true,
                'message' => 'Visibilidad actualizada correctamente',
                'data' => [
                    'id_atractivo_turistico' => $atractivo->id_atractivo_turistico,
                    'is_visible' => $atractivo->is_visible,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar visibilidad: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deleteAtractivo($id)
    {
        $atractivo = AtractivoTuristico::with('fotos')->find($id);

        if (! $atractivo) {
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
            // Las redes sociales se eliminan en cascada
            $atractivo->delete();

            if ($idDireccion) {
                DireccionGoogle::where('id_direccion', $idDireccion)->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Atractivo, dirección e imágenes eliminados correctamente en su totalidad.',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: '.$e->getMessage(),
            ], 500);
        }
    }
}
