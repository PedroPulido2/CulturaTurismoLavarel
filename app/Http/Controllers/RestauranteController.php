<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AplicaFiltrosListado;
use App\Http\Controllers\Concerns\RechazaCambioDePk;
use App\Http\Controllers\Concerns\SyncRedesSociales;
use App\Http\Resources\RestauranteAdminResource;
use App\Http\Resources\RestaurantePublicResource;
use App\Models\DireccionGoogle;
use App\Models\Foto;
use App\Models\Restaurante;
use App\Models\TipoCocina;
use App\Services\FotoStorageService;
use App\Services\PortadaService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RestauranteController extends Controller
{
    use AplicaFiltrosListado;
    use RechazaCambioDePk;
    use SyncRedesSociales;

    protected $fotos;

    public function __construct(FotoStorageService $fotos)
    {
        $this->fotos = $fotos;
    }

    public function getAllRestaurantes(Request $request)
    {
        // Vitrina pública: solo visibles, campos mínimos y paginado anti-scraping.
        // Filtros: ?search= (nombre, platos_principales) y ?id_tipo_cocina= exacto.
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        $query = Restaurante::select([
            'id_restaurante', 'nombre', 'celular', 'is_visible', 'id_tipo_cocina', 'id_direccion',
        ])
            ->where('is_visible', true)
            ->with([
                'direccion:id_direccion,direccion',
                'fotos:id_foto,url_foto,is_portada,id_restaurante',
                'tipoCocina:id_tipo_cocina,nombre',
            ]);

        $this->aplicarBusqueda($query, $request, ['nombre', 'platos_principales']);
        $this->aplicarFiltroExacto($query, $request, 'id_tipo_cocina', 'id_tipo_cocina');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => RestaurantePublicResource::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function getAllRestaurantesAdmin(Request $request)
    {
        // Gestión admin: todos (visibles + ocultos), con contacto e is_visible.
        // Filtros opcionales: ?is_visible=, ?search=, ?id_tipo_cocina= exacto.
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        $query = Restaurante::select([
            'id_restaurante', 'nombre', 'celular', 'correo',
            'is_visible', 'id_tipo_cocina', 'id_direccion',
        ])->with([
            'direccion:id_direccion,direccion',
            'fotos:id_foto,url_foto,is_portada,id_restaurante',
            'tipoCocina:id_tipo_cocina,nombre',
            'redesSociales:id_redes_sociales,nombre',
        ]);

        if ($request->has('is_visible')) {
            $query->where('is_visible', filter_var($request->is_visible, FILTER_VALIDATE_BOOLEAN));
        }

        $this->aplicarBusqueda($query, $request, ['nombre', 'platos_principales']);
        $this->aplicarFiltroExacto($query, $request, 'id_tipo_cocina', 'id_tipo_cocina');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => RestauranteAdminResource::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function getRestauranteById($id)
    {
        $restaurante = Restaurante::with(['direccion', 'fotos', 'tipoCocina', 'redesSociales'])->find($id);

        if (! $restaurante) {
            return response()->json(['success' => false, 'message' => 'Restaurante no encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => $restaurante]);
    }

    public function createRestaurante(Request $request)
    {
        $validador = Validator::make($request->all(), array_merge([
            'id_restaurante' => ['required', 'string', 'max:15', Rule::unique(Restaurante::class, 'id_restaurante')],
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'celular' => 'nullable|integer',
            'correo' => 'nullable|email|max:255',
            'horarios' => 'nullable|string',
            'propietario' => 'nullable|string|max:255',
            'capacidad' => 'nullable|string|max:255',
            'platos_principales' => 'nullable|string',
            'is_visible' => 'nullable|boolean',
            'id_tipo_cocina' => ['nullable', 'integer', Rule::exists(TipoCocina::class, 'id_tipo_cocina')],
            'fotos' => 'sometimes|array',
            'fotos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ], $this->reglasRedesSociales()));

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            $direccion = DireccionGoogle::create([
                'direccion' => $request->direccion,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
            ]);

            $restaurante = Restaurante::create([
                'id_restaurante' => $request->id_restaurante,
                'nombre' => $request->nombre,
                'celular' => $request->celular,
                'correo' => $request->correo,
                'horarios' => $request->horarios,
                'propietario' => $request->propietario,
                'capacidad' => $request->capacidad,
                'platos_principales' => $request->platos_principales,
                'is_visible' => filter_var($request->is_visible, FILTER_VALIDATE_BOOLEAN),
                'id_tipo_cocina' => $request->id_tipo_cocina,
                'id_direccion' => $direccion->id_direccion,
            ]);

            // Redes sociales del restaurante (pivote restaurante_redes_sociales)
            $this->syncRedesSociales(
                'culturayturismo.restaurante_redes_sociales',
                'id_restaurante',
                $restaurante->id_restaurante,
                $request->input('redes_sociales')
            );

            if ($request->hasFile('fotos')) {
                $fotosGuardadas = [];

                foreach ($request->file('fotos') as $index => $archivo) {
                    $rutaFoto = $this->fotos->guardar($archivo, 'restaurantes', 'restaurante_'.$restaurante->id_restaurante);
                    $fotosGuardadas[] = $rutaFoto;

                    Foto::create([
                        'url_foto' => $rutaFoto,
                        'is_portada' => $index === 0,
                        'id_restaurante' => $restaurante->id_restaurante,
                    ]);
                }
            }

            DB::commit();
            $restaurante->load(['direccion', 'fotos', 'tipoCocina', 'redesSociales']);

            return response()->json([
                'success' => true,
                'message' => 'Restaurante registrado exitosamente',
                'data' => $restaurante,
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
                'message' => 'Error al registrar el restaurante: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateVisibility(Request $request, $id)
    {
        $restaurante = Restaurante::find($id);

        if (! $restaurante) {
            return response()->json(['success' => false, 'message' => 'restaurante no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'is_visible' => 'required|boolean',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            // Se actualiza únicamente el campo de visibilidad
            $restaurante->is_visible = $request->is_visible;
            $restaurante->save();

            return response()->json([
                'success' => true,
                'message' => 'Visibilidad actualizada correctamente',
                'data' => [
                    'id_restaurante' => $restaurante->id_restaurante,
                    'is_visible' => $restaurante->is_visible,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar visibilidad: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateRestaurante(Request $request, $id)
    {
        $restaurante = Restaurante::with(['direccion', 'fotos', 'tipoCocina', 'redesSociales'])->find($id);

        if (! $restaurante) {
            return response()->json(['success' => false, 'message' => 'Restaurante no encontrado'], 404);
        }

        // El ID es la PK inmutable: el front puede reenviarlo si coincide con la URL.
        if ($respuesta = $this->rechazarCambioDePk($request, 'id_restaurante', $id)) {
            return $respuesta;
        }

        $validador = Validator::make($request->all(), array_merge([
            'nombre' => 'sometimes|string|max:255',
            'correo' => 'nullable|email|max:255',
            'celular' => 'nullable|integer',
            'horarios' => 'nullable|string',
            'propietario' => 'nullable|string|max:255',
            'capacidad' => 'nullable|string|max:255',
            'platos_principales' => 'nullable|string',
            'is_visible' => 'nullable|boolean',
            'id_tipo_cocina' => ['nullable', 'integer', Rule::exists(TipoCocina::class, 'id_tipo_cocina')],
            'nuevas_fotos' => 'sometimes|array',
            'nuevas_fotos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'fotos_a_eliminar' => 'sometimes|array',
            'fotos_a_eliminar.*' => 'integer',
            'id_foto_portada' => 'nullable|integer', // ID de foto existente que será la portada
        ], $this->reglasRedesSociales()));

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        // La portada debe ser una foto existente de este restaurante y no puede
        // eliminarse en la misma petición (se valida antes de abrir la transacción).
        if ($request->filled('id_foto_portada')) {
            $aEliminar = array_map('intval', (array) $request->input('fotos_a_eliminar', []));
            if (in_array((int) $request->input('id_foto_portada'), $aEliminar, true)) {
                return response()->json(['success' => false, 'message' => 'La foto elegida como portada no puede eliminarse en la misma petición'], 422);
            }
            $portadaPertenece = Foto::where('id_foto', $request->input('id_foto_portada'))
                ->where('id_restaurante', $id)
                ->exists();
            if (! $portadaPertenece) {
                return response()->json(['success' => false, 'message' => 'La foto elegida como portada no pertenece a este restaurante'], 422);
            }
        }

        try {
            DB::beginTransaction();

            if ($request->hasAny(['direccion', 'latitud', 'longitud'])) {
                $restaurante->direccion->update($request->only([
                    'direccion',
                    'latitud',
                    'longitud',
                ]));
            }

            $datos = $request->only([
                'nombre',
                'celular',
                'correo',
                'horarios',
                'propietario',
                'capacidad',
                'platos_principales',
                'id_tipo_cocina',
            ]);

            if ($request->has('is_visible')) {
                $datos['is_visible'] = filter_var($request->is_visible, FILTER_VALIDATE_BOOLEAN);
            }

            $restaurante->update($datos);

            // Reemplazar redes sociales si se enviaron
            if ($request->has('redes_sociales')) {
                $this->syncRedesSociales(
                    'culturayturismo.restaurante_redes_sociales',
                    'id_restaurante',
                    $id,
                    $request->input('redes_sociales')
                );
            }

            if ($request->has('fotos_a_eliminar')) {
                $fotosAEliminar = Foto::whereIn('id_foto', $request->fotos_a_eliminar)
                    ->where('id_restaurante', $id)
                    ->get();

                foreach ($fotosAEliminar as $foto) {
                    $this->fotos->eliminar($foto->url_foto);
                    $foto->delete();
                }
            }

            if ($request->hasFile('nuevas_fotos')) {
                foreach ($request->file('nuevas_fotos') as $archivo) {
                    $rutaFoto = $this->fotos->guardar($archivo, 'restaurantes', 'restaurante_'.$id);
                    $nuevasGuardadas[] = $rutaFoto;

                    Foto::create([
                        'url_foto' => $rutaFoto,
                        'is_portada' => false,
                        'id_restaurante' => $id,
                    ]);
                }
            }

            // Portada: se marca la elegida o se garantiza una si quedó sin ninguna
            // (ej. se eliminó la portada anterior).
            $portadas = app(PortadaService::class);
            if ($request->filled('id_foto_portada')) {
                $portadas->asignar('id_restaurante', $id, (int) $request->input('id_foto_portada'));
            } else {
                $portadas->asegurar('id_restaurante', $id);
            }

            DB::commit();
            $restaurante->load(['direccion', 'fotos', 'tipoCocina', 'redesSociales']);

            return response()->json([
                'success' => true,
                'message' => 'Restaurante actualizado correctamente',
                'data' => $restaurante,
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
                'message' => 'Error al actualizar el restaurante: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deleteRestaurante($id)
    {
        $restaurante = Restaurante::with('fotos')->find($id);

        if (! $restaurante) {
            return response()->json(['success' => false, 'message' => 'Restaurante no encontrado'], 404);
        }

        try {
            DB::beginTransaction();

            foreach ($restaurante->fotos as $foto) {
                $this->fotos->eliminar($foto->url_foto);
            }

            $idDireccion = $restaurante->id_direccion;

            // Fotos y redes sociales se eliminan en cascada
            $restaurante->delete();
            DireccionGoogle::where('id_direccion', $idDireccion)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Restaurante y sus imágenes eliminados correctamente',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el restaurante: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Catálogo de tipos de cocina.
     */
    public function getTiposCocina()
    {
        try {
            $tipos = TipoCocina::all();

            return response()->json(['success' => true, 'data' => $tipos]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tipos de cocina: '.$e->getMessage(),
            ], 500);
        }
    }

    public function createTipoCocina(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'nombre' => 'required|string|max:60',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            $tipo = TipoCocina::create(['nombre' => $request->nombre]);

            return response()->json(['success' => true, 'message' => 'Tipo de cocina creado correctamente', 'data' => $tipo], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el tipo de cocina: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deleteTipoCocina($id)
    {
        try {
            $tipo = TipoCocina::find($id);
            if (! $tipo) {
                return response()->json(['success' => false, 'message' => 'Tipo de cocina no encontrado'], 404);
            }
            $tipo->delete();

            return response()->json(['success' => true, 'message' => 'Tipo de cocina eliminado correctamente']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el tipo de cocina: '.$e->getMessage(),
            ], 500);
        }
    }
}
