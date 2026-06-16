<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ServicioCultural;
use App\Models\AreaArtistica;
use App\Models\TipoPerfilSc;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class ServicioCulturalController extends Controller
{
    protected $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    /**
     * Obtener todos los servicios culturales con sus relaciones.
     */
    public function getAllServicios()
    {
        try {
            $servicios = ServicioCultural::with(['areaArtistica', 'tipoPerfilSc'])->get();
            return response()->json(['success' => true, 'data' => $servicios]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los servicios culturales: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un servicio cultural específico por su ID.
     */
    public function getServicioById($id)
    {
        try {
            $servicio = ServicioCultural::with(['areaArtistica', 'tipoPerfilSc'])->find($id);

            if (!$servicio) {
                return response()->json([
                    'success' => false,
                    'message' => 'Servicio cultural no encontrado'
                ], 404);
            }

            return response()->json(['success' => true, 'data' => $servicio]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el servicio cultural: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar un nuevo servicio cultural.
     */
    public function createServicio(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'id_area_artistica' => 'required|integer|exists:areas_artisticas,id',
            'id_tipo_perfil_sc' => 'required|integer|exists:tipos_perfiles_sc,id',
            'nombre_artistico' => 'required|string|max:255',
            'telefono' => 'nullable|integer',
            'correo' => 'nullable|email|max:255',
            'contacto' => 'nullable|string|max:255',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'biografia' => 'nullable|string',
            'tipo_servicio' => 'nullable|string',
            'publico_objetivo' => 'nullable|string|max:255',
            'reconocimientos' => 'nullable|string',
            'correo_publicar' => 'nullable|email|max:255',
            'telefono_publicar' => 'nullable|integer',
            'sitio_web' => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'youtube' => 'nullable|string|max:255',
            'tiktok' => 'nullable|string|max:255',
            'otra_red' => 'nullable|string|max:255',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            $urlFoto = null;

            // Procesar y subir foto a Drive
            if ($request->hasFile('foto')) {
                $idCarpetaDestino = env('ID_CARPETA_SERVICIOS_CULTURALES');
                $archivo = $request->file('foto');

                // Nombre único basado en timestamp
                $nombreArchivo = 'sc_' . time() . '.' . $archivo->getClientOriginalExtension();

                $urlFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);
            }

            // Crear registro
            $servicio = ServicioCultural::create([
                'id_area_artistica' => $request->id_area_artistica,
                'id_tipo_perfil_sc' => $request->id_tipo_perfil_sc,
                'nombre_artistico' => $request->nombre_artistico,
                'telefono' => $request->telefono,
                'correo' => $request->correo,
                'contacto' => $request->contacto,
                'url_foto' => $urlFoto,
                'biografia' => $request->biografia,
                'tipo_servicio' => $request->tipo_servicio,
                'publico_objetivo' => $request->publico_objetivo,
                'reconocimientos' => $request->reconocimientos,
                'correo_publicar' => $request->correo_publicar,
                'telefono_publicar' => $request->telefono_publicar,
                'sitio_web' => $request->sitio_web,
                'instagram' => $request->instagram,
                'facebook' => $request->facebook,
                'youtube' => $request->youtube,
                'tiktok' => $request->tiktok,
                'otra_red' => $request->otra_red,
            ]);

            DB::commit();

            // Cargar relaciones antes de retornar
            $servicio->load(['areaArtistica', 'tipoPerfilSc']);

            return response()->json([
                'success' => true,
                'message' => 'Servicio cultural guardado exitosamente',
                'data' => $servicio
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el servicio cultural: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un servicio cultural existente.
     */
    public function updateServicio(Request $request, $id)
    {
        $servicio = ServicioCultural::find($id);

        if (!$servicio) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio cultural no encontrado'
            ], 404);
        }

        $validador = Validator::make($request->all(), [
            'id_area_artistica' => 'sometimes|integer|exists:areas_artisticas,id',
            'id_tipo_perfil_sc' => 'sometimes|integer|exists:tipos_perfiles_sc,id',
            'nombre_artistico' => 'sometimes|string|max:255',
            'telefono' => 'nullable|integer',
            'correo' => 'nullable|email|max:255',
            'contacto' => 'nullable|string|max:255',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'biografia' => 'nullable|string',
            'tipo_servicio' => 'nullable|string',
            'publico_objetivo' => 'nullable|string|max:255',
            'reconocimientos' => 'nullable|string',
            'correo_publicar' => 'nullable|email|max:255',
            'telefono_publicar' => 'nullable|integer',
            'sitio_web' => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'youtube' => 'nullable|string|max:255',
            'tiktok' => 'nullable|string|max:255',
            'otra_red' => 'nullable|string|max:255',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            // Actualizar datos de texto y relaciones
            $servicio->fill($request->only([
                'id_area_artistica',
                'id_tipo_perfil_sc',
                'nombre_artistico',
                'telefono',
                'correo',
                'contacto',
                'biografia',
                'tipo_servicio',
                'publico_objetivo',
                'reconocimientos',
                'correo_publicar',
                'telefono_publicar',
                'sitio_web',
                'instagram',
                'facebook',
                'youtube',
                'tiktok',
                'otra_red',
            ]));

            // Subir y actualizar la foto si se proporciona una nueva
            if ($request->hasFile('foto')) {
                $idCarpetaDestino = env('ID_CARPETA_SERVICIOS_CULTURALES');
                $archivo = $request->file('foto');

                // Eliminar la foto anterior de Google Drive si existe
                if ($servicio->url_foto) {
                    $this->driveService->deleteFromDrive($servicio->url_foto);
                }

                $nombreArchivo = 'sc_' . $id . '_' . time() . '.' . $archivo->getClientOriginalExtension();
                $urlFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                $servicio->url_foto = $urlFoto;
            }

            $servicio->save();

            DB::commit();

            $servicio->load(['areaArtistica', 'tipoPerfilSc']);

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

    /**
     * Eliminar un servicio cultural.
     */
    public function deleteServicio($id)
    {
        $servicio = ServicioCultural::find($id);

        if (!$servicio) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio cultural no encontrado'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Eliminar foto de Google Drive si existe
            if ($servicio->url_foto) {
                $this->driveService->deleteFromDrive($servicio->url_foto);
            }

            $servicio->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Servicio cultural e imagen eliminados correctamente.'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el servicio cultural: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener listado de áreas artísticas.
     */
    public function getAreasArtisticas()
    {
        try {
            $areas = AreaArtistica::all();
            return response()->json(['success' => true, 'data' => $areas]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener áreas artísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createAreasArtisticas(Request $request)
    {
        try {
            $validador = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255',
            ]);
            if ($validador->fails()) {
                return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
            }
            $area = AreaArtistica::create([
                'nombre' => $request->nombre,
            ]);
            return response()->json(['success' => true, 'message' => 'Área artística creada correctamente', 'data' => $area]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el área artística: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteAreasArtisticas($id)
    {
        try {
            $area = AreaArtistica::find($id);
            if (!$area) {
                return response()->json([
                    'success' => false,
                    'message' => 'Área artística no encontrada'
                ], 404);
            }
            $area->delete();
            return response()->json(['success' => true, 'message' => 'Área artística eliminada correctamente']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el área artística: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener listado de tipos de perfiles de servicios culturales.
     */
    public function getTiposPerfilesSc()
    {
        try {
            $tipos = TipoPerfilSc::all();
            return response()->json(['success' => true, 'data' => $tipos]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tipos de perfiles: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createTiposPerfilesSc(Request $request)
    {
        try {
            $validador = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255',
            ]);
            if ($validador->fails()) {
                return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
            }
            $tipo = TipoPerfilSc::create([
                'nombre' => $request->nombre,
            ]);
            return response()->json(['success' => true, 'message' => 'Tipo de perfil creado correctamente', 'data' => $tipo]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el tipo de perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteTiposPerfilesSc($id)
    {
        try {
            $tipo = TipoPerfilSc::find($id);
            if (!$tipo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de perfil no encontrado'
                ], 404);
            }
            $tipo->delete();
            return response()->json(['success' => true, 'message' => 'Tipo de perfil eliminado correctamente']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el tipo de perfil: ' . $e->getMessage()
            ], 500);
        }
    }
}
