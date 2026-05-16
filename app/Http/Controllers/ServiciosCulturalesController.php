<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ServiciosCulturales;
use App\Models\DireccionGoogle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class ServiciosCulturalesController extends Controller
{
    public function getAllServiciosCulturales()
    {
        $servicios = ServiciosCulturales::with('direccion')->get();
        return response()->json(['success' => true, 'data' => $servicios]);
    }

    public function getServicioCulturalById($id)
    {
        $servicio = ServiciosCulturales::with('direccion')->find($id);

        if (!$servicio) {
            return response()->json(['success' => false, 'message' => 'Servicio cultural no encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => $servicio]);
    }

    public function createServicioCultural(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'nombre_completo' => 'required|string|max:255',
            'direccion' => 'required|string', // Obligatorio para crear la ubicación
            'correo' => 'nullable|email|max:150',
            'celular' => 'nullable|integer'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            // 1. Crear la Dirección primero
            $direccion = DireccionGoogle::create([
                'direccion' => $request->direccion,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'google_place_id' => $request->google_place_id
            ]);

            // 2. Crear el Servicio Cultural amarrado a la dirección
            $servicioCultural = ServiciosCulturales::create([
                'nombre_completo' => $request->nombre_completo,
                'nombre_artistico' => $request->nombre_artistico,
                'celular' => $request->celular,
                'correo' => $request->correo,
                'sector' => $request->sector,
                'area_artistica' => $request->area_artistica,
                'servicio' => $request->servicio,
                'descripcion_experiencia' => $request->descripcion_experiencia,
                'facebook' => $request->facebook,
                'instagram' => $request->instagram,
                'youtube' => $request->youtube,
                'x' => $request->x,
                'id_direccion' => $direccion->id_direccion
            ]);

            DB::commit();

            // Cargamos la dirección para que el JSON de respuesta sea completo
            $servicioCultural->load('direccion');

            return response()->json([
                'success' => true,
                'message' => 'Servicio cultural registrado exitosamente',
                'data' => $servicioCultural
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el servicio cultural: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateServicioCultural(Request $request, $id)
    {
        $servicio = ServiciosCulturales::with('direccion')->find($id);

        if (!$servicio) {
            return response()->json(['success' => false, 'message' => 'Servicio cultural no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'nombre_completo' => 'sometimes|string|max:255',
            'direccion' => 'sometimes|string',
            'correo' => 'nullable|email|max:150',
            'celular' => 'nullable|integer'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            // 1. Actualizar la Dirección si vienen datos nuevos
            if ($request->hasAny(['direccion', 'latitud', 'longitud', 'google_place_id'])) {
                $servicio->direccion->update($request->only([
                    'direccion',
                    'latitud',
                    'longitud',
                    'google_place_id'
                ]));
            }

            // 2. Actualizar el Servicio Cultural
            $servicio->update($request->only([
                'nombre_completo',
                'nombre_artistico',
                'celular',
                'correo',
                'sector',
                'area_artistica',
                'servicio',
                'descripcion_experiencia',
                'facebook',
                'instagram',
                'youtube',
                'x'
            ]));

            DB::commit();

            $servicio->load('direccion');

            return response()->json([
                'success' => true,
                'message' => 'Servicio cultural actualizado correctamente',
                'data' => $servicio
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el servicio cultural: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteServicioCultural($id)
    {
        $servicio = ServiciosCulturales::find($id);

        if (!$servicio) {
            return response()->json(['success' => false, 'message' => 'Servicio cultural no encontrado'], 404);
        }

        try {
            DB::beginTransaction();

            $idDireccion = $servicio->id_direccion;

            // 1. Borrar el Servicio Cultural
            $servicio->delete();

            // 2. Borrar la Dirección asociada
            DireccionGoogle::where('id_direccion', $idDireccion)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Servicio cultural y su dirección eliminados correctamente'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el servicio cultural: ' . $e->getMessage()
            ], 500);
        }
    }
}
