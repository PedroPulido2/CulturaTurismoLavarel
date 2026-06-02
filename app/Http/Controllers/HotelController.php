<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hotel;
use App\Models\DireccionGoogle;
use App\Models\FotosHotel;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;


class HotelController extends Controller
{
    protected $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    public function getAllHoteles()
    {
        $hoteles = Hotel::with(['direccion', 'fotos'])->get();
        return response()->json(['success' => true, 'data' => $hoteles]);
    }

    public function getHotelById($id)
    {
        $hotel = Hotel::with(['direccion', 'fotos'])->find($id);

        if (!$hotel) {
            return response()->json(['success' => false, 'message' => 'Hotel no encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => $hotel]);
    }

    public function updateVisibility(Request $request, $id)
    {
        $hotel = Hotel::find($id);

        if (!$hotel) {
            return response()->json(['success' => false, 'message' => 'hotel no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'isvisible' => 'required|boolean'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            // Se actualiza únicamente el campo de visibilidad
            $hotel->isvisible = $request->isvisible;
            $hotel->save();

            return response()->json([
                'success' => true,
                'message' => 'Visibilidad actualizada correctamente',
                'data' => [
                    'id_hotel' => $hotel->id_hotel, 
                    'isvisible' => $hotel->isvisible
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar visibilidad: ' . $e->getMessage()
            ], 500);
        }
    }


    public function createHotel(Request $request)
    {
        // 1. Validación exhaustiva de los datos del Hotel
        $validador = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string',
            'correo' => 'nullable|email|max:150',
            'n_habitaciones_totales' => 'nullable|integer',
            'n_habitaciones_simples' => 'nullable|integer',
            'n_habitaciones_dobles' => 'nullable|integer',
            'n_habitaciones_suites' => 'nullable|integer',

            // Validaciones booleanas
            'petfriendly' => 'nullable|boolean',
            'acceso_discapacidad' => 'nullable|boolean',
            'parqueadero' => 'nullable|boolean',
            'hotel' => 'nullable|boolean',
            'calificacion_salud' => 'nullable|boolean',
            'visita_inspeccion_turismo' => 'nullable|boolean',

            // Fotos
            'fotos' => 'sometimes|array',
            'fotos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            //Crear la ubicación geográfica
            $direccion = DireccionGoogle::create([
                'direccion' => $request->direccion,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'google_place_id' => $request->google_place_id
            ]);

            //Crear el Hotel
            $hotel = Hotel::create([
                'nombre' => $request->nombre,
                'celular' => $request->celular,
                'correo' => $request->correo,
                'rnt' => $request->rnt,
                'nombre_contacto' => $request->nombre_contacto,
                'n_habitaciones_totales' => $request->n_habitaciones_totales,
                'n_habitaciones_simples' => $request->n_habitaciones_simples,
                'n_habitaciones_dobles' => $request->n_habitaciones_dobles,
                'n_habitaciones_suites' => $request->n_habitaciones_suites,

                // Convertimos los valores a booleanos estrictos en PHP por seguridad antes de insertarlos
                'petfriendly' => filter_var($request->petfriendly, FILTER_VALIDATE_BOOLEAN),
                'acceso_discapacidad' => filter_var($request->acceso_discapacidad, FILTER_VALIDATE_BOOLEAN),
                'parqueadero' => filter_var($request->parqueadero, FILTER_VALIDATE_BOOLEAN),
                'hotel' => filter_var($request->hotel, FILTER_VALIDATE_BOOLEAN),
                'calificacion_salud' => filter_var($request->calificacion_salud, FILTER_VALIDATE_BOOLEAN),
                'visita_inspeccion_turismo' => filter_var($request->visita_inspeccion_turismo, FILTER_VALIDATE_BOOLEAN),

                'instagram' => $request->instagram,
                'facebook' => $request->facebook,
                'whatsapp' => $request->whatsapp,
                'web' => $request->web,
                'observaciones' => $request->observaciones,
                'id_direccion' => $direccion->id_direccion
            ]);

            //Procesar y subir las fotos a Drive
            if ($request->hasFile('fotos')) {
                $idCarpetaDestino = env('ID_CARPETA_FOTOS_HOTELES');
                $archivos = $request->file('fotos');

                foreach ($archivos as $index => $archivo) {
                    $nombreArchivo = 'hotel_' . $hotel->id_hotel . '_' . time() . '_' . $index . '.' . $archivo->getClientOriginalExtension();

                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    FotosHotel::create([
                        'url_foto' => $rutaFoto,
                        'id_hotel' => $hotel->id_hotel
                    ]);
                }
            }

            DB::commit();

            $hotel->load(['direccion', 'fotos']);

            return response()->json([
                'success' => true,
                'message' => 'Hotel registrado exitosamente',
                'data' => $hotel
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el hotel: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateHotel(Request $request, $id)
    {
        $hotel = Hotel::with(['direccion', 'fotos'])->find($id);

        if (!$hotel) {
            return response()->json(['success' => false, 'message' => 'Hotel no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255',
            'direccion' => 'sometimes|string',
            'correo' => 'nullable|email|max:150',
            'n_habitaciones_totales' => 'nullable|integer',
            'n_habitaciones_simples' => 'nullable|integer',
            'n_habitaciones_dobles' => 'nullable|integer',
            'n_habitaciones_suites' => 'nullable|integer',
            
            // Booleanos
            'petfriendly' => 'nullable|boolean',
            'acceso_discapacidad' => 'nullable|boolean',
            'parqueadero' => 'nullable|boolean',
            'hotel' => 'nullable|boolean',
            'calificacion_salud' => 'nullable|boolean',
            'visita_inspeccion_turismo' => 'nullable|boolean',

            // Fotos
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

            //Actualizar Dirección
            if ($request->hasAny(['direccion', 'latitud', 'longitud', 'google_place_id'])) {
                $hotel->direccion->update($request->only([
                    'direccion',
                    'latitud',
                    'longitud',
                    'google_place_id'
                ]));
            }

            //Preparar y actualizar datos del Hotel
            $datosHotel = $request->only([
                'nombre',
                'celular',
                'correo',
                'rnt',
                'nombre_contacto',
                'n_habitaciones_totales',
                'n_habitaciones_simples',
                'n_habitaciones_dobles',
                'n_habitaciones_suites',
                'instagram',
                'facebook',
                'whatsapp',
                'web',
                'observaciones'
            ]);

            // Rutina para convertir booleanos de forma segura si vienen en el request
            $camposBooleanos = ['petfriendly', 'acceso_discapacidad', 'parqueadero', 'hotel', 'calificacion_salud', 'visita_inspeccion_turismo'];
            foreach ($camposBooleanos as $campo) {
                if ($request->has($campo)) {
                    $datosHotel[$campo] = filter_var($request->$campo, FILTER_VALIDATE_BOOLEAN);
                }
            }

            $hotel->update($datosHotel);

            //Eliminar fotos especificadas
            if ($request->has('fotos_a_eliminar')) {
                $fotosAEliminar = FotosHotel::whereIn('id_foto', $request->fotos_a_eliminar)
                    ->where('id_hotel', $id)
                    ->get();

                foreach ($fotosAEliminar as $foto) {
                    if ($foto->url_foto) {
                        $this->driveService->deleteFromDrive($foto->url_foto);
                    }
                    $foto->delete();
                }
            }

            //Subir nuevas fotos
            if ($request->hasFile('nuevas_fotos')) {
                $idCarpetaDestino = env('ID_CARPETA_FOTOS_HOTELES');
                $archivos = $request->file('nuevas_fotos');

                foreach ($archivos as $index => $archivo) {
                    $nombreArchivo = 'hotel_' . $id . '_' . time() . '_' . $index . '.' . $archivo->getClientOriginalExtension();
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    FotosHotel::create([
                        'url_foto' => $rutaFoto,
                        'id_hotel' => $id
                    ]);
                }
            }

            DB::commit();

            $hotel->load(['direccion', 'fotos']);

            return response()->json([
                'success' => true,
                'message' => 'Hotel actualizado correctamente',
                'data' => $hotel
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el hotel: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteHotel($id)
    {
        $hotel = Hotel::with('fotos')->find($id);

        if (!$hotel) {
            return response()->json(['success' => false, 'message' => 'Hotel no encontrado'], 404);
        }

        try {
            DB::beginTransaction();

            //Borrar fotos de Google Drive
            foreach ($hotel->fotos as $foto) {
                if ($foto->url_foto) {
                    $this->driveService->deleteFromDrive($foto->url_foto);
                }
            }

            $idDireccion = $hotel->id_direccion;

            //Borrar Hotel
            $hotel->delete();

            //Borrar Dirección asociada
            DireccionGoogle::where('id_direccion', $idDireccion)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Hotel y sus imágenes eliminados correctamente'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el hotel: ' . $e->getMessage()
            ], 500);
        }
    }
}
