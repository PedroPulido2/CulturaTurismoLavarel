<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AplicaFiltrosListado;
use App\Models\Agencia;
use App\Models\EspecialidadTuristica;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EspecialidadTuristicaController extends Controller
{
    use AplicaFiltrosListado;

    /**
     * Listado público. Filtros opcionales: ?id_agencia=AGE-001 exacto, ?search= sobre nombre.
     */
    public function getEspecialidadesTuristicas(Request $request)
    {
        try {
            $query = EspecialidadTuristica::query()->with('agencia:id_agencia,nombre');

            $this->aplicarFiltroExacto($query, $request, 'id_agencia', 'id_agencia');
            $this->aplicarBusqueda($query, $request, ['nombre']);

            $items = $query->orderBy('id_especialidad_turistica')->get();

            return response()->json(['success' => true, 'data' => $items]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener especialidades turísticas: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getEspecialidadTuristicaById($id)
    {
        $item = EspecialidadTuristica::with('agencia:id_agencia,nombre')->find($id);

        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Especialidad turística no encontrada'], 404);
        }

        return response()->json(['success' => true, 'data' => $item]);
    }

    public function createEspecialidadTuristica(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'id_agencia' => ['required', 'string', 'max:15', Rule::exists(Agencia::class, 'id_agencia')],
            'nombre' => [
                'required', 'string', 'max:120',
                Rule::unique(EspecialidadTuristica::class, 'nombre')->where(function ($query) use ($request) {
                    return $query->where('id_agencia', $request->input('id_agencia'));
                }),
            ],
        ], [
            'nombre.unique' => 'Esta agencia ya tiene registrada esa especialidad turística.',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            $item = EspecialidadTuristica::create([
                'id_agencia' => $request->input('id_agencia'),
                'nombre' => $request->input('nombre'),
            ]);
            $item->load('agencia:id_agencia,nombre');

            return response()->json([
                'success' => true,
                'message' => 'Especialidad turística creada correctamente',
                'data' => $item,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la especialidad turística: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateEspecialidadTuristica(Request $request, $id)
    {
        $item = EspecialidadTuristica::find($id);

        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Especialidad turística no encontrada'], 404);
        }

        $idAgencia = $request->input('id_agencia', $item->id_agencia);

        $validador = Validator::make($request->all(), [
            'id_agencia' => ['sometimes', 'string', 'max:15', Rule::exists(Agencia::class, 'id_agencia')],
            'nombre' => [
                'sometimes', 'string', 'max:120',
                Rule::unique(EspecialidadTuristica::class, 'nombre')
                    ->where(fn ($query) => $query->where('id_agencia', $idAgencia))
                    ->ignore($item->getKey(), $item->getKeyName()),
            ],
        ], [
            'nombre.unique' => 'Esta agencia ya tiene registrada esa especialidad turística.',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            $item->update($request->only(['id_agencia', 'nombre']));
            $item->load('agencia:id_agencia,nombre');

            return response()->json([
                'success' => true,
                'message' => 'Especialidad turística actualizada correctamente',
                'data' => $item,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la especialidad turística: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deleteEspecialidadTuristica($id)
    {
        $item = EspecialidadTuristica::find($id);

        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Especialidad turística no encontrada'], 404);
        }

        try {
            $item->delete();

            return response()->json(['success' => true, 'message' => 'Especialidad turística eliminada correctamente']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la especialidad turística: '.$e->getMessage(),
            ], 500);
        }
    }
}
