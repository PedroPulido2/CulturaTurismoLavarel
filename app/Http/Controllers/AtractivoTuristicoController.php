<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AplicaFiltrosListado;
use App\Http\Controllers\Concerns\RechazaCambioDePk;
use App\Http\Controllers\Concerns\SyncRedesSociales;
use App\Http\Resources\AtractivoAdminResource;
use App\Http\Resources\AtractivoPublicResource;
use App\Models\AtractivoTuristico;
use App\Models\DireccionGoogle;
use App\Models\Foto;
use App\Services\FotoStorageService;
use App\Services\PortadaService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AtractivoTuristicoController extends Controller
{
    use AplicaFiltrosListado;
    use RechazaCambioDePk;
    use SyncRedesSociales;

    protected $fotos;

    public function __construct(FotoStorageService $fotos)
    {
        $this->fotos = $fotos;
    }

    public function getAllAtractivos(Request $request)
    {
        // Vitrina pública: solo visibles, campos mínimos y paginado anti-scraping.
        // Filtros: ?search= (nombre, descripcion) y ?tipo= exacto.
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        $query = AtractivoTuristico::select([
            'id_atractivo_turistico', 'nombre', 'tipo', 'descripcion', 'horario', 'is_visible', 'id_direccion',
        ])
            ->where('is_visible', true)
            ->with([
                'direccion:id_direccion,direccion',
                'fotos:id_foto,url_foto,is_portada,id_atractivo_turistico',
            ]);

        $this->aplicarBusqueda($query, $request, ['nombre', 'descripcion']);
        $this->aplicarFiltroExacto($query, $request, 'tipo', 'tipo');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AtractivoPublicResource::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function getAllAtractivosAdmin(Request $request)
    {
        // Gestión admin: todos (visibles + ocultos), con contacto, dirección e is_visible.
        // Filtros opcionales: ?is_visible=true|false, ?search= (nombre, descripcion), ?tipo= exacto.
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        $query = AtractivoTuristico::select([
            'id_atractivo_turistico', 'nombre', 'tipo', 'descripcion', 'telefono',
            'horario', 'precio', 'is_visible', 'id_direccion',
        ])->with([
            'direccion:id_direccion,direccion',
            'fotos:id_foto,url_foto,is_portada,id_atractivo_turistico',
        ]);

        if ($request->has('is_visible')) {
            $query->where('is_visible', filter_var($request->is_visible, FILTER_VALIDATE_BOOLEAN));
        }

        $this->aplicarBusqueda($query, $request, ['nombre', 'descripcion']);
        $this->aplicarFiltroExacto($query, $request, 'tipo', 'tipo');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AtractivoAdminResource::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
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

            // Procesar y guardar multiples fotos
            if ($request->hasFile('fotos')) {
                $fotosGuardadas = [];

                foreach ($request->file('fotos') as $index => $archivo) {
                    // Guarda el registro en la base de datos (la primera foto queda como portada)
                    $rutaFoto = $this->fotos->guardar($archivo, 'atractivos', 'atractivo_'.$atractivo->id_atractivo_turistico);
                    $fotosGuardadas[] = $rutaFoto;

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
            $revertirFotos = DB::transactionLevel() > 0;
            DB::rollBack();
            if ($revertirFotos) {
                foreach (($fotosGuardadas ?? []) as $urlFoto) {
                    $this->fotos->eliminar($urlFoto);
                }
            }

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

        // El ID es la PK inmutable: el front puede reenviarlo si coincide con la URL.
        if ($respuesta = $this->rechazarCambioDePk($request, 'id_atractivo_turistico', $id)) {
            return $respuesta;
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
            'id_foto_portada' => 'nullable|integer', // ID de foto existente que será la portada
        ], $this->reglasRedesSociales()));

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        // La portada debe ser una foto existente de este atractivo y no puede
        // eliminarse en la misma petición (se valida antes de abrir la transacción).
        if ($request->filled('id_foto_portada')) {
            $aEliminar = array_map('intval', (array) $request->input('fotos_a_eliminar', []));
            if (in_array((int) $request->input('id_foto_portada'), $aEliminar, true)) {
                return response()->json(['success' => false, 'message' => 'La foto elegida como portada no puede eliminarse en la misma petición'], 422);
            }
            $portadaPertenece = Foto::where('id_foto', $request->input('id_foto_portada'))
                ->where('id_atractivo_turistico', $id)
                ->exists();
            if (! $portadaPertenece) {
                return response()->json(['success' => false, 'message' => 'La foto elegida como portada no pertenece a este atractivo'], 422);
            }
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

            // Eliminar fotos específicas (del almacenamiento local y de la DB)
            if ($request->has('fotos_a_eliminar')) {
                $fotosAEliminar = Foto::whereIn('id_foto', $request->fotos_a_eliminar)
                    ->where('id_atractivo_turistico', $id)
                    ->get();

                foreach ($fotosAEliminar as $foto) {
                    $this->fotos->eliminar($foto->url_foto);
                    $foto->delete(); // La borramos de la base de datos
                }
            }

            // Subir y guardar las nuevas fotos
            if ($request->hasFile('nuevas_fotos')) {
                foreach ($request->file('nuevas_fotos') as $archivo) {
                    $rutaFoto = $this->fotos->guardar($archivo, 'atractivos', 'atractivo_'.$id);
                    $nuevasGuardadas[] = $rutaFoto;

                    Foto::create([
                        'url_foto' => $rutaFoto,
                        'is_portada' => false,
                        'id_atractivo_turistico' => $id,
                    ]);
                }
            }

            // Portada: se marca la elegida o se garantiza una si quedó sin ninguna
            // (ej. se eliminó la portada anterior).
            $portadas = app(PortadaService::class);
            if ($request->filled('id_foto_portada')) {
                $portadas->asignar('id_atractivo_turistico', $id, (int) $request->input('id_foto_portada'));
            } else {
                $portadas->asegurar('id_atractivo_turistico', $id);
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
            $revertirFotos = DB::transactionLevel() > 0;
            DB::rollBack();
            if ($revertirFotos) {
                foreach (($nuevasGuardadas ?? []) as $urlFoto) {
                    $this->fotos->eliminar($urlFoto);
                }
            }

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
                $this->fotos->eliminar($foto->url_foto);
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
