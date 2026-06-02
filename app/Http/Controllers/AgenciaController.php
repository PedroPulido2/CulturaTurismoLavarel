<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agencia;
use App\Models\FotosAgencia;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class AgenciaController extends Controller
{
    protected $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    public function getAllAgencias()
    {
        $agencias = Agencia::with('fotos')->get();
        return response()->json(['success' => true, 'data' => $agencias]);
    }

    public function getAgenciaById($id)
    {
        $agencia = Agencia::with('fotos')->find($id);

        if (!$agencia) {
            return response()->json(['success' => false, 'message' => 'Agencia no encontrada'], 404);
        }

        return response()->json(['success' => true, 'data' => $agencia]);
    }

    public function createAgencia(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'celular' => 'nullable|string|max:255',
            'correo' => 'nullable|email|max:150',
            'n_empleados_asociados' => 'nullable|integer',
            'fotos' => 'sometimes|array',
            'fotos.*' => 'image|mimes:jpeg,png,jpg|max:5120'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            $agencia = Agencia::create($request->only([
                'nombre',
                'celular',
                'correo',
                'nit',
                'rnt',
                'tipo',
                'instagram',
                'facebook',
                'whatsapp',
                'web',
                'representante_legal',
                'n_empleados_asociados',
                'especialidad_turistica',
                'destinos_principales',
                'observaciones'
            ]));

            // Procesar fotos si las hay
            if ($request->hasFile('fotos')) {
                $idCarpetaDestino = env('ID_CARPETA_FOTOS_AGENCIAS');
                $archivos = $request->file('fotos');

                foreach ($archivos as $index => $archivo) {
                    $nombreArchivo = 'agencia_' . $agencia->id_agencia . '_' . time() . '_' . $index . '.' . $archivo->getClientOriginalExtension();
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    FotosAgencia::create([
                        'url_foto' => $rutaFoto,
                        'id_agencia' => $agencia->id_agencia
                    ]);
                }
            }

            DB::commit();
            $agencia->load('fotos');

            return response()->json([
                'success' => true,
                'message' => 'Agencia registrada exitosamente',
                'data' => $agencia
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la agencia: ' . $e->getMessage()
            ], 500);
        }
    }

        public function updateVisibility(Request $request, $id)
    {
        $agencia = Agencia::find($id);

        if (!$agencia) {
            return response()->json(['success' => false, 'message' => 'agencia no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'isvisible' => 'required|boolean'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            // Se actualiza únicamente el campo de visibilidad
            $agencia->isvisible = $request->isvisible;
            $agencia->save();

            return response()->json([
                'success' => true,
                'message' => 'Visibilidad actualizada correctamente',
                'data' => [
                    'id_agencia' => $agencia->id_agencia, 
                    'isvisible' => $agencia->isvisible
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar visibilidad: ' . $e->getMessage()
            ], 500);
        }
    }
    public function updateAgencia(Request $request, $id)
    {
        $agencia = Agencia::with('fotos')->find($id);

        if (!$agencia) {
            return response()->json(['success' => false, 'message' => 'Agencia no encontrada'], 404);
        }

        $validador = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255',
            'correo' => 'nullable|email|max:150',
            'n_empleados_asociados' => 'nullable|integer',
            'nuevas_fotos' => 'sometimes|array',
            'nuevas_fotos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'fotos_a_eliminar' => 'sometimes|array',
            'fotos_a_eliminar.*' => 'integer'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            DB::beginTransaction();

            $agencia->update($request->only([
                'nombre',
                'celular',
                'correo',
                'nit',
                'rnt',
                'tipo',
                'instagram',
                'facebook',
                'whatsapp',
                'web',
                'representante_legal',
                'n_empleados_asociados',
                'especialidad_turistica',
                'destinos_principales',
                'observaciones'
            ]));

            if ($request->has('fotos_a_eliminar')) {
                $fotosAEliminar = FotosAgencia::whereIn('id_foto', $request->fotos_a_eliminar)
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
                    $nombreArchivo = 'agencia_' . $id . '_' . time() . '_' . $index . '.' . $archivo->getClientOriginalExtension();
                    $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

                    FotosAgencia::create([
                        'url_foto' => $rutaFoto,
                        'id_agencia' => $id
                    ]);
                }
            }

            DB::commit();
            $agencia->load('fotos');

            return response()->json([
                'success' => true,
                'message' => 'Agencia actualizada correctamente',
                'data' => $agencia
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la agencia: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteAgencia($id)
    {
        $agencia = Agencia::with('fotos')->find($id);

        if (!$agencia) {
            return response()->json(['success' => false, 'message' => 'Agencia no encontrada'], 404);
        }

        try {
            DB::beginTransaction();

            foreach ($agencia->fotos as $foto) {
                if ($foto->url_foto) {
                    $this->driveService->deleteFromDrive($foto->url_foto);
                }
            }

            $agencia->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Agencia y sus imágenes eliminadas correctamente'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la agencia: ' . $e->getMessage()
            ], 500);
        }
    }
}
