<?php

namespace App\Http\Controllers;

use App\Models\Competencia;
use App\Models\Disponibilidad;
use App\Models\Especialidad;
use App\Models\Guia;
use App\Models\TipoPublico;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GuiaController extends Controller
{
    protected function relacionesGuia(): array
    {
        return ['especialidad', 'disponibilidad', 'competencia', 'tipoPublico'];
    }

    public function getAllGuias()
    {
        $guias = Guia::with($this->relacionesGuia())->get();

        return response()->json(['success' => true, 'data' => $guias]);
    }

    public function getGuiaById($id)
    {
        $guia = Guia::with($this->relacionesGuia())->find($id);

        if (! $guia) {
            return response()->json(['success' => false, 'message' => 'Guía no encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => $guia]);
    }

    public function createGuia(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'id_guia' => ['required', 'string', 'max:15', Rule::unique(Guia::class, 'id_guia')],
            'nombre' => 'required|string|max:255',
            'n_cedula' => ['required', 'integer', Rule::unique(Guia::class, 'n_cedula')],
            'rnt' => 'nullable|string|max:80',
            'celular' => 'nullable|integer',
            'correo' => ['nullable', 'email', 'max:150', Rule::unique(Guia::class, 'correo')],
            'num_tarjeta_profesional' => ['nullable', 'integer', Rule::unique(Guia::class, 'num_tarjeta_profesional')],
            'rango_anios_experiencia' => 'nullable|string|max:50',
            'idiomas' => 'nullable|string|max:255',
            'principales_atractivos' => 'nullable|string',
            'asociacion' => 'nullable|string|max:150',
            'is_visible' => 'nullable|boolean',
            'id_especialidad' => ['required', 'integer', Rule::exists(Especialidad::class, 'id_especialidad')],
            'id_disponibilidad' => ['required', 'integer', Rule::exists(Disponibilidad::class, 'id_disponibilidad')],
            'id_competencias' => ['required', 'integer', Rule::exists(Competencia::class, 'id_competencias')],
            'id_tipo_publico' => ['required', 'integer', Rule::exists(TipoPublico::class, 'id_tipo_publico')],
        ], [
            'n_cedula.unique' => 'Ya existe un guía registrado con este número de cédula.',
            'correo.unique' => 'Este correo electrónico ya está en uso por otro guía.',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            $datos = $request->only([
                'id_guia',
                'nombre',
                'n_cedula',
                'rnt',
                'celular',
                'correo',
                'num_tarjeta_profesional',
                'rango_anios_experiencia',
                'idiomas',
                'principales_atractivos',
                'asociacion',
                'id_especialidad',
                'id_disponibilidad',
                'id_competencias',
                'id_tipo_publico',
            ]);

            if ($request->has('is_visible')) {
                $datos['is_visible'] = filter_var($request->is_visible, FILTER_VALIDATE_BOOLEAN);
            }

            $guia = Guia::create($datos);
            $guia->load($this->relacionesGuia());

            return response()->json([
                'success' => true,
                'message' => 'Guía registrado exitosamente',
                'data' => $guia,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el guía: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateGuia(Request $request, $id)
    {
        $guia = Guia::find($id);

        if (! $guia) {
            return response()->json(['success' => false, 'message' => 'Guía no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            // Validamos que sea único, pero ignorando el ID del guía actual para que pueda guardar sin cambiar la cédula
            'n_cedula' => ['sometimes', 'integer', Rule::unique(Guia::class, 'n_cedula')->ignore($id, 'id_guia')],
            'nombre' => 'sometimes|string|max:255',
            'correo' => ['nullable', 'email', 'max:150', Rule::unique(Guia::class, 'correo')->ignore($id, 'id_guia')],
            'num_tarjeta_profesional' => ['nullable', 'integer', Rule::unique(Guia::class, 'num_tarjeta_profesional')->ignore($id, 'id_guia')],
            'rnt' => 'nullable|string|max:80',
            'celular' => 'nullable|integer',
            'rango_anios_experiencia' => 'nullable|string|max:50',
            'idiomas' => 'nullable|string|max:255',
            'principales_atractivos' => 'nullable|string',
            'asociacion' => 'nullable|string|max:150',
            'is_visible' => 'nullable|boolean',
            'id_especialidad' => ['sometimes', 'integer', Rule::exists(Especialidad::class, 'id_especialidad')],
            'id_disponibilidad' => ['sometimes', 'integer', Rule::exists(Disponibilidad::class, 'id_disponibilidad')],
            'id_competencias' => ['sometimes', 'integer', Rule::exists(Competencia::class, 'id_competencias')],
            'id_tipo_publico' => ['sometimes', 'integer', Rule::exists(TipoPublico::class, 'id_tipo_publico')],
        ], [
            'n_cedula.unique' => 'El número de cédula ya está ocupado por otro guía.',
            'correo.unique' => 'El correo ya está en uso por otro guía.',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            $datos = $request->only([
                'nombre',
                'n_cedula',
                'rnt',
                'celular',
                'correo',
                'num_tarjeta_profesional',
                'rango_anios_experiencia',
                'idiomas',
                'principales_atractivos',
                'asociacion',
                'id_especialidad',
                'id_disponibilidad',
                'id_competencias',
                'id_tipo_publico',
            ]);

            if ($request->has('is_visible')) {
                $datos['is_visible'] = filter_var($request->is_visible, FILTER_VALIDATE_BOOLEAN);
            }

            $guia->update($datos);
            $guia->load($this->relacionesGuia());

            return response()->json([
                'success' => true,
                'message' => 'Guía actualizado correctamente',
                'data' => $guia,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el guía: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deleteGuia($id)
    {
        $guia = Guia::find($id);

        if (! $guia) {
            return response()->json(['success' => false, 'message' => 'Guía no encontrado'], 404);
        }

        try {
            $guia->delete();

            return response()->json([
                'success' => true,
                'message' => 'Guía eliminado correctamente',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el guía: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Catálogo de especialidades de guía.
     */
    public function getEspecialidades()
    {
        return response()->json(['success' => true, 'data' => Especialidad::all()]);
    }

    public function createEspecialidad(Request $request)
    {
        $validador = Validator::make($request->all(), ['nombre' => 'required|string|max:80']);
        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            $item = Especialidad::create(['nombre' => $request->nombre]);

            return response()->json(['success' => true, 'message' => 'Especialidad creada correctamente', 'data' => $item], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al crear la especialidad: '.$e->getMessage()], 500);
        }
    }

    public function deleteEspecialidad($id)
    {
        $item = Especialidad::find($id);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Especialidad no encontrada'], 404);
        }

        try {
            $item->delete();

            return response()->json(['success' => true, 'message' => 'Especialidad eliminada correctamente']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al eliminar la especialidad: '.$e->getMessage()], 500);
        }
    }

    /**
     * Catálogo de disponibilidades de guía.
     */
    public function getDisponibilidades()
    {
        return response()->json(['success' => true, 'data' => Disponibilidad::all()]);
    }

    public function createDisponibilidad(Request $request)
    {
        $validador = Validator::make($request->all(), ['nombre' => 'required|string|max:80']);
        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            $item = Disponibilidad::create(['nombre' => $request->nombre]);

            return response()->json(['success' => true, 'message' => 'Disponibilidad creada correctamente', 'data' => $item], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al crear la disponibilidad: '.$e->getMessage()], 500);
        }
    }

    public function deleteDisponibilidad($id)
    {
        $item = Disponibilidad::find($id);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Disponibilidad no encontrada'], 404);
        }

        try {
            $item->delete();

            return response()->json(['success' => true, 'message' => 'Disponibilidad eliminada correctamente']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al eliminar la disponibilidad: '.$e->getMessage()], 500);
        }
    }

    /**
     * Catálogo de competencias de guía.
     */
    public function getCompetencias()
    {
        return response()->json(['success' => true, 'data' => Competencia::all()]);
    }

    public function createCompetencia(Request $request)
    {
        $validador = Validator::make($request->all(), ['nombre' => 'required|string|max:80']);
        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            $item = Competencia::create(['nombre' => $request->nombre]);

            return response()->json(['success' => true, 'message' => 'Competencia creada correctamente', 'data' => $item], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al crear la competencia: '.$e->getMessage()], 500);
        }
    }

    public function deleteCompetencia($id)
    {
        $item = Competencia::find($id);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Competencia no encontrada'], 404);
        }

        try {
            $item->delete();

            return response()->json(['success' => true, 'message' => 'Competencia eliminada correctamente']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al eliminar la competencia: '.$e->getMessage()], 500);
        }
    }

    /**
     * Catálogo de tipos de público de guía.
     */
    public function getTiposPublico()
    {
        return response()->json(['success' => true, 'data' => TipoPublico::all()]);
    }

    public function createTipoPublico(Request $request)
    {
        $validador = Validator::make($request->all(), ['nombre' => 'required|string|max:80']);
        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            $item = TipoPublico::create(['nombre' => $request->nombre]);

            return response()->json(['success' => true, 'message' => 'Tipo de público creado correctamente', 'data' => $item], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al crear el tipo de público: '.$e->getMessage()], 500);
        }
    }

    public function deleteTipoPublico($id)
    {
        $item = TipoPublico::find($id);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Tipo de público no encontrado'], 404);
        }

        try {
            $item->delete();

            return response()->json(['success' => true, 'message' => 'Tipo de público eliminado correctamente']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al eliminar el tipo de público: '.$e->getMessage()], 500);
        }
    }
}
