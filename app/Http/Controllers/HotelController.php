<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SyncRedesSociales;
use App\Models\DireccionGoogle;
use App\Models\Foto;
use App\Models\Hotel;
use App\Services\GoogleDriveService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HotelController extends Controller
{
    use SyncRedesSociales;

    protected $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    public function getAllHoteles()
    {
        $hoteles = Hotel::with(['direccion', 'fotos', 'redesSociales'])->get();

        return response()->json(['success' => true, 'data' => $hoteles]);
    }

    public function getHotelById($id)
    {
        $hotel = Hotel::with(['direccion', 'fotos', 'redesSociales'])->find($id);

        if (! $hotel) {
            return response()->json(['success' => false, 'message' => 'Hotel no encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => $hotel]);
    }

    public function updateVisibility(Request $request, $id)
    {
        $hotel = Hotel::find($id);

        if (! $hotel) {
            return response()->json(['success' => false, 'message' => 'hotel no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'is_visible' => 'required|boolean',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            // Se actualiza únicamente el campo de visibilidad
            $hotel->is_visible = $request->is_visible;
            $hotel->save();

            return response()->json([
                'success' => true,
                'message' => 'Visibilidad actualizada correctamente',
                'data' => [
                    'id_hotel' => $hotel->id_hotel,
                    'is_visible' => $hotel->is_visible,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar visibilidad: '.$e->getMessage(),
            ], 500);
        }
    }

    public function createHotel(Request $request)
    {
        // 1. Validación exhaustiva de los datos del Hotel
        $validador = Validator::make($request->all(), array_merge([
            'id_hotel' => ['required', 'string', 'max:15', Rule::unique(Hotel::class, 'id_hotel')],
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'rnt' => 'nullable|string|max:80',
            'celular' => 'nullable|integer',
            'correo' => ['nullable', 'email', 'max:150', Rule::unique(Hotel::class, 'correo')],
            'nombre_contacto' => 'nullable|string|max:150',
            'n_habitaciones_totales' => 'nullable|integer',
            'n_habitaciones_simples' => 'nullable|integer',
            'n_habitaciones_dobles' => 'nullable|integer',
            'n_habitaciones_suites' => 'nullable|integer',

            // Validaciones booleanas
            'petfriendly' => 'nullable|boolean',
            'acceso_discapacidad' => 'nullable|boolean',
            'parqueadero' => 'nullable|boolean',
            'restaurante' => 'nullable|boolean',
            'visita_inspeccion_turismo' => 'nullable|boolean',

            'calificacion_salud' => 'nullable|numeric',
            'observacion' => 'nullable|string',
            'is_visible' => 'nullable|boolean',

            // Fotos
            'fotos' => 'sometimes|array',
            'fotos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ], $this->reglasRedesSociales()));

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            // Crear la ubicación geográfica
            $direccion = DireccionGoogle::create([
                'direccion' => $request->direccion,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
            ]);

            // Crear el Hotel
            $hotel = Hotel::create([
                'id_hotel' => $request->id_hotel,
                'nombre' => $request->nombre,
                'rnt' => $request->rnt,
                'celular' => $request->celular,
                'correo' => $request->correo,
                'nombre_contacto' => $request->nombre_contacto,
                'n_habitaciones_totales' => $request->n_habitaciones_totales,
                'n_habitaciones_simples' => $request->n_habitaciones_simples,
                'n_habitaciones_dobles' => $request->n_habitaciones_dobles,
                'n_habitaciones_suites' => $request->n_habitaciones_suites,

                // Convertimos los valores a booleanos estrictos en PHP por seguridad antes de insertarlos
                'petfriendly' => filter_var($request->petfriendly, FILTER_VALIDATE_BOOLEAN),
                'acceso_discapacidad' => filter_var($request->acceso_discapacidad, FILTER_VALIDATE_BOOLEAN),
                'parqueadero' => filter_var($request->parqueadero, FILTER_VALIDATE_BOOLEAN),
                'restaurante' => filter_var($request->restaurante, FILTER_VALIDATE_BOOLEAN),
                'visita_inspeccion_turismo' => filter_var($request->visita_inspeccion_turismo, FILTER_VALIDATE_BOOLEAN),

                'calificacion_salud' => $request->calificacion_salud,
                'observacion' => $request->observacion,
                'is_visible' => filter_var($request->is_visible, FILTER_VALIDATE_BOOLEAN),
                'id_direccion' => $direccion->id_direccion,
            ]);

            // Redes sociales del hotel (pivote hotel_redes_sociales)
            $this->syncRedesSociales(
                'culturayturismo.hotel_redes_sociales',
                'id_hotel',
                $hotel->id_hotel,
                $request->input('redes_sociales')
            );

            // Procesar y subir las fotos a Drive
            if ($request->hasFile('fotos')) {
                $idCarpetaDestino = env('ID_CARPETA_FOTOS_HOTELES');
                $archivos = $request->file('fotos');

                foreach ($archivos as $index => $archivo) {
                    $nombreArchivo = 'hotel_'.$hotel->id_hotel.'_'.time().'_'.$index.'.'.$archivo->getClientOriginalExtension();

                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    Foto::create([
                        'url_foto' => $rutaFoto,
                        'is_portada' => $index === 0,
                        'id_hotel' => $hotel->id_hotel,
                    ]);
                }
            }

            DB::commit();

            $hotel->load(['direccion', 'fotos', 'redesSociales']);

            return response()->json([
                'success' => true,
                'message' => 'Hotel registrado exitosamente',
                'data' => $hotel,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el hotel: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateHotel(Request $request, $id)
    {
        $hotel = Hotel::with(['direccion', 'fotos', 'redesSociales'])->find($id);

        if (! $hotel) {
            return response()->json(['success' => false, 'message' => 'Hotel no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), array_merge([
            'nombre' => 'sometimes|string|max:255',
            'direccion' => 'sometimes|string',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'rnt' => 'nullable|string|max:80',
            'celular' => 'nullable|integer',
            'correo' => ['nullable', 'email', 'max:150', Rule::unique(Hotel::class, 'correo')->ignore($id, 'id_hotel')],
            'nombre_contacto' => 'nullable|string|max:150',
            'n_habitaciones_totales' => 'nullable|integer',
            'n_habitaciones_simples' => 'nullable|integer',
            'n_habitaciones_dobles' => 'nullable|integer',
            'n_habitaciones_suites' => 'nullable|integer',

            // Booleanos
            'petfriendly' => 'nullable|boolean',
            'acceso_discapacidad' => 'nullable|boolean',
            'parqueadero' => 'nullable|boolean',
            'restaurante' => 'nullable|boolean',
            'visita_inspeccion_turismo' => 'nullable|boolean',

            'calificacion_salud' => 'nullable|numeric',
            'observacion' => 'nullable|string',
            'is_visible' => 'nullable|boolean',

            // Fotos
            'nuevas_fotos' => 'sometimes|array',
            'nuevas_fotos.*' => 'image|mimes:jpeg,png,jpg|max:5120',
            'fotos_a_eliminar' => 'sometimes|array',
            'fotos_a_eliminar.*' => 'integer',
        ], $this->reglasRedesSociales()));

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            // Actualizar Dirección
            if ($request->hasAny(['direccion', 'latitud', 'longitud'])) {
                $hotel->direccion->update($request->only([
                    'direccion',
                    'latitud',
                    'longitud',
                ]));
            }

            // Preparar y actualizar datos del Hotel
            $datosHotel = $request->only([
                'nombre',
                'rnt',
                'celular',
                'correo',
                'nombre_contacto',
                'n_habitaciones_totales',
                'n_habitaciones_simples',
                'n_habitaciones_dobles',
                'n_habitaciones_suites',
                'calificacion_salud',
                'observacion',
            ]);

            // Rutina para convertir booleanos de forma segura si vienen en el request
            $camposBooleanos = ['petfriendly', 'acceso_discapacidad', 'parqueadero', 'restaurante', 'visita_inspeccion_turismo', 'is_visible'];
            foreach ($camposBooleanos as $campo) {
                if ($request->has($campo)) {
                    $datosHotel[$campo] = filter_var($request->$campo, FILTER_VALIDATE_BOOLEAN);
                }
            }

            $hotel->update($datosHotel);

            // Reemplazar redes sociales si se enviaron
            if ($request->has('redes_sociales')) {
                $this->syncRedesSociales(
                    'culturayturismo.hotel_redes_sociales',
                    'id_hotel',
                    $id,
                    $request->input('redes_sociales')
                );
            }

            // Eliminar fotos especificadas
            if ($request->has('fotos_a_eliminar')) {
                $fotosAEliminar = Foto::whereIn('id_foto', $request->fotos_a_eliminar)
                    ->where('id_hotel', $id)
                    ->get();

                foreach ($fotosAEliminar as $foto) {
                    if ($foto->url_foto) {
                        $this->driveService->deleteFromDrive($foto->url_foto);
                    }
                    $foto->delete();
                }
            }

            // Subir nuevas fotos
            if ($request->hasFile('nuevas_fotos')) {
                $idCarpetaDestino = env('ID_CARPETA_FOTOS_HOTELES');
                $archivos = $request->file('nuevas_fotos');

                foreach ($archivos as $index => $archivo) {
                    $nombreArchivo = 'hotel_'.$id.'_'.time().'_'.$index.'.'.$archivo->getClientOriginalExtension();
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    Foto::create([
                        'url_foto' => $rutaFoto,
                        'is_portada' => false,
                        'id_hotel' => $id,
                    ]);
                }
            }

            DB::commit();

            $hotel->load(['direccion', 'fotos', 'redesSociales']);

            return response()->json([
                'success' => true,
                'message' => 'Hotel actualizado correctamente',
                'data' => $hotel,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el hotel: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deleteHotel($id)
    {
        $hotel = Hotel::with('fotos')->find($id);

        if (! $hotel) {
            return response()->json(['success' => false, 'message' => 'Hotel no encontrado'], 404);
        }

        try {
            DB::beginTransaction();

            // Borrar fotos de Google Drive
            foreach ($hotel->fotos as $foto) {
                if ($foto->url_foto) {
                    $this->driveService->deleteFromDrive($foto->url_foto);
                }
            }

            $idDireccion = $hotel->id_direccion;

            // Borrar Hotel (fotos y redes sociales se eliminan en cascada)
            $hotel->delete();

            // Borrar Dirección asociada
            DireccionGoogle::where('id_direccion', $idDireccion)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Hotel y sus imágenes eliminados correctamente',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el hotel: '.$e->getMessage(),
            ], 500);
        }
    }
}
