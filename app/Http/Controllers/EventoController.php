<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventoAdminResource;
use App\Http\Resources\EventoPublicResource;
use App\Models\DireccionGoogle;
use App\Models\Evento;
use App\Models\Foto;
use App\Services\GoogleDriveService;
use App\Services\PortadaService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EventoController extends Controller
{
    protected $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    public function getAllEventos(Request $request)
    {
        // Vitrina pública: solo visibles, campos mínimos y paginado anti-scraping.
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        $paginator = Evento::select([
            'id_evento', 'nombre', 'fecha_inicio', 'fecha_fin', 'tipo',
            'organizador', 'is_visible', 'id_direccion',
        ])
            ->where('is_visible', true)
            ->with([
                'direccion:id_direccion,direccion',
                'fotos:id_foto,url_foto,is_portada,id_evento',
            ])
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => EventoPublicResource::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function getAllEventosAdmin(Request $request)
    {
        // Gestión admin: todos (visibles + ocultos), con contacto e is_visible.
        // Filtro opcional: ?is_visible=true|false
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        $query = Evento::select([
            'id_evento', 'nombre', 'fecha_inicio', 'fecha_fin', 'tipo',
            'organizador', 'contacto', 'is_visible', 'id_direccion',
        ])->with([
            'direccion:id_direccion,direccion',
            'fotos:id_foto,url_foto,is_portada,id_evento',
        ]);

        if ($request->has('is_visible')) {
            $query->where('is_visible', filter_var($request->is_visible, FILTER_VALIDATE_BOOLEAN));
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => EventoAdminResource::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function getEventoById($id)
    {
        $evento = Evento::with(['direccion', 'fotos'])->find($id);

        if (! $evento) {
            return response()->json(['success' => false, 'message' => 'Evento no encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => $evento]);
    }

    public function createEvento(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'id_evento' => ['required', 'string', 'max:15', Rule::unique(Evento::class, 'id_evento')],
            'nombre' => 'required|string|max:80',
            'descripcion' => 'nullable|string',
            'tipo' => 'nullable|string|max:50',
            'organizador' => 'nullable|string|max:45',
            'contacto' => 'nullable|integer',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'asistentes_estimados' => 'nullable|integer',
            'asistentes_reales' => 'nullable|integer',
            'impacto_economico' => 'nullable|numeric',
            'observaciones' => 'nullable|string',
            'is_visible' => 'nullable|boolean',
            'direccion' => 'required|string',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'fotos' => 'sometimes|array', // Galería del evento
            'fotos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            // Crear ubicación
            $direccion = DireccionGoogle::create([
                'direccion' => $request->direccion,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
            ]);

            $idCarpetaDestino = env('ID_CARPETA_FOTOS_EVENTOS');

            // Crear el evento con su PK alfanumérica (VARCHAR(15)) asignada por el cliente
            $datos = $request->only([
                'id_evento',
                'nombre',
                'descripcion',
                'tipo',
                'organizador',
                'contacto',
                'fecha_inicio',
                'fecha_fin',
                'asistentes_estimados',
                'asistentes_reales',
                'impacto_economico',
                'observaciones',
            ]);

            if ($request->has('is_visible')) {
                $datos['is_visible'] = filter_var($request->is_visible, FILTER_VALIDATE_BOOLEAN);
            }

            $datos['id_direccion'] = $direccion->id_direccion;

            $evento = Evento::create($datos);

            // Subir la galería de fotos del evento
            if ($request->hasFile('fotos')) {
                $archivos = $request->file('fotos');

                foreach ($archivos as $index => $archivo) {
                    $nombreArchivo = 'evento_galeria_'.$evento->id_evento.'_'.time().'_'.$index.'.'.$archivo->getClientOriginalExtension();
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    Foto::create([
                        'url_foto' => $rutaFoto,
                        'is_portada' => $index === 0,
                        'id_evento' => $evento->id_evento,
                    ]);
                }
            }

            DB::commit();
            $evento->load(['direccion', 'fotos']);

            return response()->json([
                'success' => true,
                'message' => 'Evento registrado exitosamente',
                'data' => $evento,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el evento: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateEvento(Request $request, $id)
    {
        $evento = Evento::with(['direccion', 'fotos'])->find($id);

        if (! $evento) {
            return response()->json(['success' => false, 'message' => 'Evento no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:80',
            'descripcion' => 'nullable|string',
            'tipo' => 'nullable|string|max:50',
            'organizador' => 'nullable|string|max:45',
            'contacto' => 'nullable|integer',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'asistentes_estimados' => 'nullable|integer',
            'asistentes_reales' => 'nullable|integer',
            'impacto_economico' => 'nullable|numeric',
            'observaciones' => 'nullable|string',
            'is_visible' => 'nullable|boolean',
            'nuevas_fotos' => 'sometimes|array', // Nuevas fotos para la galería
            'nuevas_fotos.*' => 'image|mimes:jpeg,png,jpg|max:5120',
            'fotos_a_eliminar' => 'sometimes|array', // IDs de fotos de la galería a borrar
            'fotos_a_eliminar.*' => 'integer',
            'id_foto_portada' => 'nullable|integer', // ID de foto existente que será la portada
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        // La portada debe ser una foto existente de este evento y no puede
        // eliminarse en la misma petición (se valida antes de abrir la transacción).
        if ($request->filled('id_foto_portada')) {
            $aEliminar = array_map('intval', (array) $request->input('fotos_a_eliminar', []));
            if (in_array((int) $request->input('id_foto_portada'), $aEliminar, true)) {
                return response()->json(['success' => false, 'message' => 'La foto elegida como portada no puede eliminarse en la misma petición'], 422);
            }
            $portadaPertenece = Foto::where('id_foto', $request->input('id_foto_portada'))
                ->where('id_evento', $id)
                ->exists();
            if (! $portadaPertenece) {
                return response()->json(['success' => false, 'message' => 'La foto elegida como portada no pertenece a este evento'], 422);
            }
        }

        try {
            DB::beginTransaction();

            // Actualizar Dirección
            if ($request->hasAny(['direccion', 'latitud', 'longitud'])) {
                $evento->direccion->update($request->only([
                    'direccion',
                    'latitud',
                    'longitud',
                ]));
            }

            // Actualizar datos básicos (id_evento y codigo no se actualizan: son PK)
            $datos = $request->only([
                'nombre',
                'descripcion',
                'tipo',
                'organizador',
                'contacto',
                'fecha_inicio',
                'fecha_fin',
                'asistentes_estimados',
                'asistentes_reales',
                'impacto_economico',
                'observaciones',
            ]);

            if ($request->has('is_visible')) {
                $datos['is_visible'] = filter_var($request->is_visible, FILTER_VALIDATE_BOOLEAN);
            }

            $evento->update($datos);

            $idCarpetaDestino = env('ID_CARPETA_FOTOS_EVENTOS');

            // Eliminar fotos de la galería especificadas
            if ($request->has('fotos_a_eliminar')) {
                $fotosAEliminar = Foto::whereIn('id_foto', $request->fotos_a_eliminar)
                    ->where('id_evento', $id)
                    ->get();

                foreach ($fotosAEliminar as $foto) {
                    if ($foto->url_foto) {
                        $this->driveService->deleteFromDrive($foto->url_foto);
                    }
                    $foto->delete();
                }
            }

            // Agregar nuevas fotos a la galería
            if ($request->hasFile('nuevas_fotos')) {
                $archivos = $request->file('nuevas_fotos');

                foreach ($archivos as $index => $archivo) {
                    $nombreArchivo = 'evento_galeria_'.$id.'_'.time().'_'.$index.'.'.$archivo->getClientOriginalExtension();
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    Foto::create([
                        'url_foto' => $rutaFoto,
                        'is_portada' => false,
                        'id_evento' => $id,
                    ]);
                }
            }

            // Portada: se marca la elegida o se garantiza una si quedó sin ninguna
            // (ej. se eliminó la portada anterior).
            $portadas = app(PortadaService::class);
            if ($request->filled('id_foto_portada')) {
                $portadas->asignar('id_evento', $id, (int) $request->input('id_foto_portada'));
            } else {
                $portadas->asegurar('id_evento', $id);
            }

            DB::commit();
            $evento->load(['direccion', 'fotos']);

            return response()->json([
                'success' => true,
                'message' => 'Evento actualizado correctamente',
                'data' => $evento,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el evento: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateVisibility(Request $request, $id)
    {
        $evento = Evento::find($id);

        if (! $evento) {
            return response()->json(['success' => false, 'message' => 'Evento no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'is_visible' => 'required|boolean',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            // Se actualiza únicamente el campo de visibilidad
            $evento->is_visible = $request->is_visible;
            $evento->save();

            return response()->json([
                'success' => true,
                'message' => 'Visibilidad actualizada correctamente',
                'data' => [
                    'id_evento' => $evento->id_evento,
                    'is_visible' => $evento->is_visible,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar visibilidad: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deleteEvento($id)
    {
        $evento = Evento::with('fotos')->find($id);

        if (! $evento) {
            return response()->json(['success' => false, 'message' => 'Evento no encontrado'], 404);
        }

        try {
            DB::beginTransaction();

            // Eliminar todas las fotos de la galería de Drive
            foreach ($evento->fotos as $foto) {
                if ($foto->url_foto) {
                    $this->driveService->deleteFromDrive($foto->url_foto);
                }
            }

            $idDireccion = $evento->id_direccion;

            // El borrado del evento disparará el CASCADE en fotos
            $evento->delete();

            // Limpiar la dirección asociada
            DireccionGoogle::where('id_direccion', $idDireccion)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Evento y todos sus archivos asociados eliminados correctamente',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el evento: '.$e->getMessage(),
            ], 500);
        }
    }
}
