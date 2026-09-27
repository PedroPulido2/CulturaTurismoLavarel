<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SyncRedesSociales;
use App\Models\Agencia;
use App\Models\Foto;
use App\Models\TipoAgencia;
use App\Services\GoogleDriveService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AgenciaController extends Controller
{
    use SyncRedesSociales;

    protected $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    public function getAllAgencias()
    {
        $agencias = Agencia::with(['tipoAgencia', 'especialidades', 'fotos', 'redesSociales'])->get();

        return response()->json(['success' => true, 'data' => $agencias]);
    }

    public function getAgenciaById($id)
    {
        $agencia = Agencia::with(['tipoAgencia', 'especialidades', 'fotos', 'redesSociales'])->find($id);

        if (! $agencia) {
            return response()->json(['success' => false, 'message' => 'Agencia no encontrada'], 404);
        }

        return response()->json(['success' => true, 'data' => $agencia]);
    }

    public function createAgencia(Request $request)
    {
        $validador = Validator::make($request->all(), array_merge([
            'id_agencia' => ['required', 'string', 'max:15', Rule::unique(Agencia::class, 'id_agencia')],
            'nombre' => 'required|string|max:255',
            'nit' => 'nullable|string|max:80',
            'rnt' => 'nullable|string|max:80',
            'celular' => 'nullable|integer',
            'correo' => 'nullable|email|max:150',
            'representante_legal' => 'nullable|string|max:150',
            'n_empleados_asociados' => 'nullable|integer',
            'destinos_principales' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'is_visible' => 'nullable|boolean',
            'id_tipo_agencia' => ['required', 'integer', Rule::exists(TipoAgencia::class, 'id_tipo_agencia')],
            'especialidades' => 'sometimes|array',
            'especialidades.*' => 'string|max:120',
            'fotos' => 'sometimes|array',
            'fotos.*' => 'image|mimes:jpeg,png,jpg|max:5120',
        ], $this->reglasRedesSociales()));

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            $agencia = Agencia::create([
                'id_agencia' => $request->id_agencia,
                'nit' => $request->nit,
                'rnt' => $request->rnt,
                'nombre' => $request->nombre,
                'celular' => $request->celular,
                'correo' => $request->correo,
                'representante_legal' => $request->representante_legal,
                'n_empleados_asociados' => $request->n_empleados_asociados,
                'destinos_principales' => $request->destinos_principales,
                'observaciones' => $request->observaciones,
                'is_visible' => filter_var($request->is_visible, FILTER_VALIDATE_BOOLEAN),
                'id_tipo_agencia' => $request->id_tipo_agencia,
            ]);

            // Especialidades turísticas propias de la agencia
            $this->syncEspecialidades($agencia, $request->input('especialidades'));

            // Redes sociales de la agencia (pivote agencia_redes_sociales)
            $this->syncRedesSociales(
                'culturayturismo.agencia_redes_sociales',
                'id_agencia',
                $agencia->id_agencia,
                $request->input('redes_sociales')
            );

            // Procesar fotos si las hay
            if ($request->hasFile('fotos')) {
                $idCarpetaDestino = env('ID_CARPETA_FOTOS_AGENCIAS');
                $archivos = $request->file('fotos');

                foreach ($archivos as $index => $archivo) {
                    $nombreArchivo = 'agencia_'.$agencia->id_agencia.'_'.time().'_'.$index.'.'.$archivo->getClientOriginalExtension();
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    Foto::create([
                        'url_foto' => $rutaFoto,
                        'is_portada' => $index === 0,
                        'id_agencia' => $agencia->id_agencia,
                    ]);
                }
            }

            DB::commit();
            $agencia->load(['tipoAgencia', 'especialidades', 'fotos', 'redesSociales']);

            return response()->json([
                'success' => true,
                'message' => 'Agencia registrada exitosamente',
                'data' => $agencia,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la agencia: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateVisibility(Request $request, $id)
    {
        $agencia = Agencia::find($id);

        if (! $agencia) {
            return response()->json(['success' => false, 'message' => 'agencia no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'is_visible' => 'required|boolean',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            // Se actualiza únicamente el campo de visibilidad
            $agencia->is_visible = $request->is_visible;
            $agencia->save();

            return response()->json([
                'success' => true,
                'message' => 'Visibilidad actualizada correctamente',
                'data' => [
                    'id_agencia' => $agencia->id_agencia,
                    'is_visible' => $agencia->is_visible,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar visibilidad: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateAgencia(Request $request, $id)
    {
        $agencia = Agencia::with(['tipoAgencia', 'especialidades', 'fotos', 'redesSociales'])->find($id);

        if (! $agencia) {
            return response()->json(['success' => false, 'message' => 'Agencia no encontrada'], 404);
        }

        $validador = Validator::make($request->all(), array_merge([
            'nombre' => 'sometimes|string|max:255',
            'nit' => 'nullable|string|max:80',
            'rnt' => 'nullable|string|max:80',
            'celular' => 'nullable|integer',
            'correo' => 'nullable|email|max:150',
            'representante_legal' => 'nullable|string|max:150',
            'n_empleados_asociados' => 'nullable|integer',
            'destinos_principales' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'is_visible' => 'nullable|boolean',
            'id_tipo_agencia' => ['sometimes', 'integer', Rule::exists(TipoAgencia::class, 'id_tipo_agencia')],
            'especialidades' => 'sometimes|array',
            'especialidades.*' => 'string|max:120',
            'nuevas_fotos' => 'sometimes|array',
            'nuevas_fotos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'fotos_a_eliminar' => 'sometimes|array',
            'fotos_a_eliminar.*' => 'integer',
        ], $this->reglasRedesSociales()));

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            $datos = $request->only([
                'nit',
                'rnt',
                'nombre',
                'celular',
                'correo',
                'representante_legal',
                'n_empleados_asociados',
                'destinos_principales',
                'observaciones',
                'id_tipo_agencia',
            ]);

            if ($request->has('is_visible')) {
                $datos['is_visible'] = filter_var($request->is_visible, FILTER_VALIDATE_BOOLEAN);
            }

            $agencia->update($datos);

            // Reemplazar especialidades turísticas si se enviaron
            if ($request->has('especialidades')) {
                $agencia->especialidades()->delete();
                $this->syncEspecialidades($agencia, $request->input('especialidades'));
            }

            // Reemplazar redes sociales si se enviaron
            if ($request->has('redes_sociales')) {
                $this->syncRedesSociales(
                    'culturayturismo.agencia_redes_sociales',
                    'id_agencia',
                    $id,
                    $request->input('redes_sociales')
                );
            }

            if ($request->has('fotos_a_eliminar')) {
                $fotosAEliminar = Foto::whereIn('id_foto', $request->fotos_a_eliminar)
                    ->where('id_agencia', $id)
                    ->get();

                foreach ($fotosAEliminar as $foto) {
                    if ($foto->url_foto) {
                        $this->driveService->deleteFromDrive($foto->url_foto);
                    }
                    $foto->delete();
                }
            }

            if ($request->hasFile('nuevas_fotos')) {
                $idCarpetaDestino = env('ID_CARPETA_FOTOS_AGENCIAS');
                $archivos = $request->file('nuevas_fotos');

                foreach ($archivos as $index => $archivo) {
                    $nombreArchivo = 'agencia_'.$id.'_'.time().'_'.$index.'.'.$archivo->getClientOriginalExtension();
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    Foto::create([
                        'url_foto' => $rutaFoto,
                        'is_portada' => false,
                        'id_agencia' => $id,
                    ]);
                }
            }

            DB::commit();
            $agencia->load(['tipoAgencia', 'especialidades', 'fotos', 'redesSociales']);

            return response()->json([
                'success' => true,
                'message' => 'Agencia actualizada correctamente',
                'data' => $agencia,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la agencia: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deleteAgencia($id)
    {
        $agencia = Agencia::with('fotos')->find($id);

        if (! $agencia) {
            return response()->json(['success' => false, 'message' => 'Agencia no encontrada'], 404);
        }

        try {
            DB::beginTransaction();

            foreach ($agencia->fotos as $foto) {
                if ($foto->url_foto) {
                    $this->driveService->deleteFromDrive($foto->url_foto);
                }
            }

            // Fotos, especialidades turísticas y redes sociales se eliminan en cascada
            $agencia->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Agencia y sus imágenes eliminadas correctamente',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la agencia: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Inserta las especialidades turísticas de la agencia evitando duplicados
     * (la BD exige UNIQUE por agencia + nombre).
     */
    protected function syncEspecialidades(Agencia $agencia, $especialidades): void
    {
        if (empty($especialidades)) {
            return;
        }

        foreach (array_unique($especialidades) as $nombre) {
            $agencia->especialidades()->create(['nombre' => $nombre]);
        }
    }

    /**
     * Catálogo de tipos de agencia.
     */
    public function getTiposAgencia()
    {
        try {
            $tipos = TipoAgencia::all();

            return response()->json(['success' => true, 'data' => $tipos]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tipos de agencia: '.$e->getMessage(),
            ], 500);
        }
    }

    public function createTipoAgencia(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'nombre' => 'required|string|max:45',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            $tipo = TipoAgencia::create(['nombre' => $request->nombre]);

            return response()->json(['success' => true, 'message' => 'Tipo de agencia creado correctamente', 'data' => $tipo], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el tipo de agencia: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deleteTipoAgencia($id)
    {
        try {
            $tipo = TipoAgencia::find($id);
            if (! $tipo) {
                return response()->json(['success' => false, 'message' => 'Tipo de agencia no encontrado'], 404);
            }
            $tipo->delete();

            return response()->json(['success' => true, 'message' => 'Tipo de agencia eliminado correctamente']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el tipo de agencia: '.$e->getMessage(),
            ], 500);
        }
    }
}
