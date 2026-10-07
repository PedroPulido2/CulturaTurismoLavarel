<?php

namespace App\Http\Controllers;

use App\Models\AreaArtistica;
use App\Models\Foto;
use App\Models\PublicoDirigido;
use App\Models\ServicioCultural;
use App\Models\TipoServicio;
use App\Services\GoogleDriveService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ServicioCulturalController extends Controller
{
    protected $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    protected function relacionesServicio(): array
    {
        return ['tipoServicio', 'publicoDirigido', 'areaArtistica', 'fotos'];
    }

    /**
     * Obtener todos los servicios culturales con sus relaciones.
     */
    public function getAllServicios()
    {
        try {
            $servicios = ServicioCultural::with($this->relacionesServicio())->get();

            return response()->json(['success' => true, 'data' => $servicios]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los servicios culturales: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener un servicio cultural específico por su ID.
     */
    public function getServicioById($id)
    {
        try {
            $servicio = ServicioCultural::with($this->relacionesServicio())->find($id);

            if (! $servicio) {
                return response()->json([
                    'success' => false,
                    'message' => 'Servicio cultural no encontrado',
                ], 404);
            }

            return response()->json(['success' => true, 'data' => $servicio]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el servicio cultural: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registrar un nuevo servicio cultural.
     */
    public function createServicio(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'id_servicio_cultural' => ['required', 'integer', Rule::unique(ServicioCultural::class, 'id_servicio_cultural')],
            'id_tipo_servicio' => ['required', 'integer', Rule::exists(TipoServicio::class, 'id_tipo_servicio')],
            'id_publico_dirigido' => ['nullable', 'integer', Rule::exists(PublicoDirigido::class, 'id_publico_dirigido')],
            'id_area_artistica' => ['required', 'integer', Rule::exists(AreaArtistica::class, 'id_area_artistica')],
            'nit' => ['nullable', 'integer', Rule::unique(ServicioCultural::class, 'nit')],
            'nombre' => 'nullable|string|max:255',
            'telefono' => 'nullable|integer',
            'correo' => ['required', 'email', 'max:255', Rule::unique(ServicioCultural::class, 'correo')],
            'contacto' => 'nullable|string|max:255',
            'biografia' => 'nullable|string',
            'reconocimientos' => 'nullable|string',
            'fotos' => 'sometimes|array',
            'fotos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ], [
            'correo.unique' => 'Este correo electrónico ya está registrado en otro servicio cultural.',
            'nit.unique' => 'Este NIT ya está registrado en otro servicio cultural.',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            // Crear registro
            $servicio = ServicioCultural::create([
                'id_servicio_cultural' => $request->id_servicio_cultural,
                'id_tipo_servicio' => $request->id_tipo_servicio,
                'id_publico_dirigido' => $request->id_publico_dirigido,
                'id_area_artistica' => $request->id_area_artistica,
                'nit' => $request->nit,
                'nombre' => $request->nombre,
                'telefono' => $request->telefono,
                'correo' => $request->correo,
                'contacto' => $request->contacto,
                'biografia' => $request->biografia,
                'reconocimientos' => $request->reconocimientos,
            ]);

            // Procesar y subir fotos a Drive (galería en la tabla unificada fotos)
            if ($request->hasFile('fotos')) {
                $idCarpetaDestino = env('ID_CARPETA_SERVICIOS_CULTURALES');
                $archivos = $request->file('fotos');

                foreach ($archivos as $index => $archivo) {
                    $nombreArchivo = 'sc_'.$servicio->id_servicio_cultural.'_'.time().'_'.$index.'.'.$archivo->getClientOriginalExtension();
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    Foto::create([
                        'url_foto' => $rutaFoto,
                        'is_portada' => $index === 0,
                        'id_servicio_cultural' => $servicio->id_servicio_cultural,
                    ]);
                }
            }

            DB::commit();

            // Cargar relaciones antes de retornar
            $servicio->load($this->relacionesServicio());

            return response()->json([
                'success' => true,
                'message' => 'Servicio cultural guardado exitosamente',
                'data' => $servicio,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el servicio cultural: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar un servicio cultural existente.
     */
    public function updateServicio(Request $request, $id)
    {
        $servicio = ServicioCultural::find($id);

        if (! $servicio) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio cultural no encontrado',
            ], 404);
        }

        $validador = Validator::make($request->all(), [
            'id_tipo_servicio' => ['sometimes', 'integer', Rule::exists(TipoServicio::class, 'id_tipo_servicio')],
            'id_publico_dirigido' => ['nullable', 'integer', Rule::exists(PublicoDirigido::class, 'id_publico_dirigido')],
            'id_area_artistica' => ['sometimes', 'integer', Rule::exists(AreaArtistica::class, 'id_area_artistica')],
            'nit' => ['sometimes', 'nullable', 'integer', Rule::unique(ServicioCultural::class, 'nit')->ignore($id, 'id_servicio_cultural')],
            'nombre' => 'nullable|string|max:255',
            'telefono' => 'nullable|integer',
            'correo' => ['sometimes', 'required', 'email', 'max:255', Rule::unique(ServicioCultural::class, 'correo')->ignore($id, 'id_servicio_cultural')],
            'contacto' => 'nullable|string|max:255',
            'biografia' => 'nullable|string',
            'reconocimientos' => 'nullable|string',
            'nuevas_fotos' => 'sometimes|array',
            'nuevas_fotos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'fotos_a_eliminar' => 'sometimes|array',
            'fotos_a_eliminar.*' => 'integer',
        ], [
            'correo.unique' => 'Este correo electrónico ya está registrado en otro servicio cultural.',
            'nit.unique' => 'Este NIT ya está registrado en otro servicio cultural.',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            // Actualizar datos de texto y relaciones
            $servicio->fill($request->only([
                'id_tipo_servicio',
                'id_publico_dirigido',
                'id_area_artistica',
                'nit',
                'nombre',
                'telefono',
                'correo',
                'contacto',
                'biografia',
                'reconocimientos',
            ]));

            $servicio->save();

            // Eliminar fotos especificadas (de Drive y de la DB)
            if ($request->has('fotos_a_eliminar')) {
                $fotosAEliminar = Foto::whereIn('id_foto', $request->fotos_a_eliminar)
                    ->where('id_servicio_cultural', $id)
                    ->get();

                foreach ($fotosAEliminar as $foto) {
                    if ($foto->url_foto) {
                        $this->driveService->deleteFromDrive($foto->url_foto);
                    }
                    $foto->delete();
                }
            }

            // Subir y guardar las nuevas fotos
            if ($request->hasFile('nuevas_fotos')) {
                $idCarpetaDestino = env('ID_CARPETA_SERVICIOS_CULTURALES');
                $archivos = $request->file('nuevas_fotos');

                foreach ($archivos as $index => $archivo) {
                    $nombreArchivo = 'sc_'.$id.'_'.time().'_'.$index.'.'.$archivo->getClientOriginalExtension();
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    Foto::create([
                        'url_foto' => $rutaFoto,
                        'is_portada' => false,
                        'id_servicio_cultural' => $id,
                    ]);
                }
            }

            DB::commit();

            $servicio->load($this->relacionesServicio());

            return response()->json([
                'success' => true,
                'message' => 'Servicio cultural actualizado correctamente',
                'data' => $servicio,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el servicio cultural: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un servicio cultural.
     */
    public function deleteServicio($id)
    {
        $servicio = ServicioCultural::with('fotos')->find($id);

        if (! $servicio) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio cultural no encontrado',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Eliminar fotos de Google Drive
            foreach ($servicio->fotos as $foto) {
                if ($foto->url_foto) {
                    $this->driveService->deleteFromDrive($foto->url_foto);
                }
            }

            // Las fotos se eliminan en cascada
            $servicio->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Servicio cultural e imágenes eliminados correctamente.',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el servicio cultural: '.$e->getMessage(),
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
                'message' => 'Error al obtener áreas artísticas: '.$e->getMessage(),
            ], 500);
        }
    }

    public function createAreasArtisticas(Request $request)
    {
        try {
            $validador = Validator::make($request->all(), [
                'nombre' => 'required|string|max:80',
            ]);
            if ($validador->fails()) {
                return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
            }
            $area = AreaArtistica::create([
                'nombre' => $request->nombre,
            ]);

            return response()->json(['success' => true, 'message' => 'Área artística creada correctamente', 'data' => $area], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el área artística: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deleteAreasArtisticas($id)
    {
        try {
            $area = AreaArtistica::find($id);
            if (! $area) {
                return response()->json([
                    'success' => false,
                    'message' => 'Área artística no encontrada',
                ], 404);
            }
            $area->delete();

            return response()->json(['success' => true, 'message' => 'Área artística eliminada correctamente']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el área artística: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener listado de tipos de servicio cultural.
     */
    public function getTiposServicio()
    {
        try {
            $tipos = TipoServicio::all();

            return response()->json(['success' => true, 'data' => $tipos]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tipos de servicio: '.$e->getMessage(),
            ], 500);
        }
    }

    public function createTipoServicio(Request $request)
    {
        try {
            $validador = Validator::make($request->all(), [
                'nombre' => 'required|string|max:120',
            ]);
            if ($validador->fails()) {
                return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
            }
            $tipo = TipoServicio::create([
                'nombre' => $request->nombre,
            ]);

            return response()->json(['success' => true, 'message' => 'Tipo de servicio creado correctamente', 'data' => $tipo], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el tipo de servicio: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deleteTipoServicio($id)
    {
        try {
            $tipo = TipoServicio::find($id);
            if (! $tipo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de servicio no encontrado',
                ], 404);
            }
            $tipo->delete();

            return response()->json(['success' => true, 'message' => 'Tipo de servicio eliminado correctamente']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el tipo de servicio: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener listado de públicos dirigidos.
     */
    public function getPublicoDirigido()
    {
        try {
            $publicos = PublicoDirigido::all();

            return response()->json(['success' => true, 'data' => $publicos]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener públicos dirigidos: '.$e->getMessage(),
            ], 500);
        }
    }

    public function createPublicoDirigido(Request $request)
    {
        try {
            $validador = Validator::make($request->all(), [
                'nombre' => 'required|string|max:45',
            ]);
            if ($validador->fails()) {
                return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
            }
            $publico = PublicoDirigido::create([
                'nombre' => $request->nombre,
            ]);

            return response()->json(['success' => true, 'message' => 'Público dirigido creado correctamente', 'data' => $publico], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el público dirigido: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deletePublicoDirigido($id)
    {
        try {
            $publico = PublicoDirigido::find($id);
            if (! $publico) {
                return response()->json([
                    'success' => false,
                    'message' => 'Público dirigido no encontrado',
                ], 404);
            }
            $publico->delete();

            return response()->json(['success' => true, 'message' => 'Público dirigido eliminado correctamente']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el público dirigido: '.$e->getMessage(),
            ], 500);
        }
    }
}
