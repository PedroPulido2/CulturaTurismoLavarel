<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Evento;
use App\Models\DireccionGoogle;
use App\Models\FotosEvento;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class EventoController extends Controller
{
    protected $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    public function getAllEventos()
    {
        $eventos = Evento::with(['direccion', 'fotos'])->get();
        return response()->json(['success' => true, 'data' => $eventos]);
    }

    public function getEventoById($id)
    {
        $evento = Evento::with(['direccion', 'fotos'])->find($id);

        if (!$evento) {
            return response()->json(['success' => false, 'message' => 'Evento no encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => $evento]);
    }

    public function createEvento(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'url_foto' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // Validación del Flyer
            'fotos' => 'sometimes|array', // Validación de la galería
            'fotos.*' => 'image|mimes:jpeg,png,jpg|max:5120'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            //Crear ubicación
            $direccion = DireccionGoogle::create([
                'direccion' => $request->direccion,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'google_place_id' => $request->google_place_id
            ]);

            $idCarpetaDestino = env('ID_CARPETA_FOTOS_EVENTOS');

            //Crear el evento base temporalmente para obtener el id_evento
            $evento = Evento::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'tipo' => $request->tipo,
                'organizador' => $request->organizador,
                'contacto' => $request->contacto,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
                'asistentes_estimados' => $request->asistentes_estimados,
                'impacto_economico' => $request->impacto_economico,
                'estado' => $request->estado,
                'observaciones' => $request->observaciones,
                'id_direccion' => $direccion->id_direccion,
                'url_foto' => null
            ]);

            //Subir el Flyer si existe y actualizar el campo
            if ($request->hasFile('url_foto')) {
                $flyer = $request->file('url_foto');
                $nombreFlyer = 'evento_flyer_' . $evento->id_evento . '.' . $flyer->getClientOriginalExtension();

                $evento->url_foto = $this->driveService->uploadToDrive($flyer, $nombreFlyer, $idCarpetaDestino);
                $evento->save();
            }

            //Subir la galería de fotos del evento realizado
            if ($request->hasFile('fotos')) {
                $archivos = $request->file('fotos');

                foreach ($archivos as $index => $archivo) {
                    $nombreArchivo = 'evento_galeria_' . $evento->id_evento . '_' . time() . '_' . $index . '.' . $archivo->getClientOriginalExtension();
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    FotosEvento::create([
                        'url_foto' => $rutaFoto,
                        'id_evento' => $evento->id_evento
                    ]);
                }
            }

            DB::commit();
            $evento->load(['direccion', 'fotos']);

            return response()->json([
                'success' => true,
                'message' => 'Evento registrado exitosamente',
                'data' => $evento
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el evento: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateEvento(Request $request, $id)
    {
        $evento = Evento::with(['direccion', 'fotos'])->find($id);

        if (!$evento) {
            return response()->json(['success' => false, 'message' => 'Evento no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'url_foto' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // Nuevo flyer
            'nuevas_fotos' => 'sometimes|array', // Nuevas fotos para la galería
            'nuevas_fotos.*' => 'image|mimes:jpeg,png,jpg|max:5120',
            'fotos_a_eliminar' => 'sometimes|array', // IDs de fotos de la galería a borrar
            'fotos_a_eliminar.*' => 'integer'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            //Actualizar Dirección
            if ($request->hasAny(['direccion', 'latitud', 'longitud', 'google_place_id'])) {
                $evento->direccion->update($request->only([
                    'direccion',
                    'latitud',
                    'longitud',
                    'google_place_id'
                ]));
            }

            //Actualizar datos básicos
            $evento->update($request->only([
                'nombre',
                'descripcion',
                'tipo',
                'organizador',
                'contacto',
                'fecha_inicio',
                'fecha_fin',
                'asistentes_estimados',
                'impacto_economico',
                'estado',
                'observaciones'
            ]));

            $idCarpetaDestino = env('ID_CARPETA_FOTOS_EVENTOS');

            // Procesar cambio de Flyer (url_foto)
            if ($request->hasFile('url_foto')) {
                // Eliminar flyer anterior si existe
                if ($evento->url_foto) {
                    $this->driveService->deleteFromDrive($evento->url_foto);
                }

                $flyer = $request->file('url_foto');
                $nombreFlyer = 'evento_flyer_' . $id . '_' . time() . '.' . $flyer->getClientOriginalExtension();
                $evento->url_foto = $this->driveService->uploadToDrive($flyer, $nombreFlyer, $idCarpetaDestino);
                $evento->save();
            }

            //Eliminar fotos de la galería especificadas
            if ($request->has('fotos_a_eliminar')) {
                $fotosAEliminar = FotosEvento::whereIn('id_foto', $request->fotos_a_eliminar)
                    ->where('id_evento', $id)
                    ->get();

                foreach ($fotosAEliminar as $foto) {
                    if ($foto->url_foto) {
                        $this->driveService->deleteFromDrive($foto->url_foto);
                    }
                    $foto->delete();
                }
            }

            //Agregar nuevas fotos a la galería
            if ($request->hasFile('nuevas_fotos')) {
                $archivos = $request->file('nuevas_fotos');

                foreach ($archivos as $index => $archivo) {
                    $nombreArchivo = 'evento_galeria_' . $id . '_' . time() . '_' . $index . '.' . $archivo->getClientOriginalExtension();
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    FotosEvento::create([
                        'url_foto' => $rutaFoto,
                        'id_evento' => $id
                    ]);
                }
            }

            DB::commit();
            $evento->load(['direccion', 'fotos']);

            return response()->json([
                'success' => true,
                'message' => 'Evento actualizado correctamente',
                'data' => $evento
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el evento: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteEvento($id)
    {
        $evento = Evento::with('fotos')->find($id);

        if (!$evento) {
            return response()->json(['success' => false, 'message' => 'Evento no encontrado'], 404);
        }

        try {
            DB::beginTransaction();

            //Eliminar el Flyer de Drive
            if ($evento->url_foto) {
                $this->driveService->deleteFromDrive($evento->url_foto);
            }

            //Eliminar todas las fotos de la galería de Drive
            foreach ($evento->fotos as $foto) {
                if ($foto->url_foto) {
                    $this->driveService->deleteFromDrive($foto->url_foto);
                }
            }

            $idDireccion = $evento->id_direccion;

            //El borrado del evento disparará el CASCADE en FotosEvento
            $evento->delete();

            //Limpiar la dirección asociada
            DireccionGoogle::where('id_direccion', $idDireccion)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Evento y todos sus archivos asociados eliminados correctamente'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el evento: ' . $e->getMessage()
            ], 500);
        }
    }
}
