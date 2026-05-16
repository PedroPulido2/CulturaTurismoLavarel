<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Guia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Exception;

class GuiaController extends Controller
{
    public function getAllGuias()
    {
        $guias = Guia::all();
        return response()->json(['success' => true, 'data' => $guias]);
    }

    public function getGuiaById($id)
    {
        $guia = Guia::find($id);

        if (!$guia) {
            return response()->json(['success' => false, 'message' => 'Guía no encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => $guia]);
    }

    public function createGuia(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'n_cedula' => ['required', 'integer', Rule::unique(Guia::class, 'n_cedula')],
            'nombre' => 'required|string|max:255',
            'correo' => ['nullable', 'email', 'max:150', Rule::unique(Guia::class, 'correo')],
            'celular' => 'nullable|integer',
            'rnt' => 'nullable|string|max:45',
        ], [
            'n_cedula.unique' => 'Ya existe un guía registrado con este número de cédula.',
            'correo.unique' => 'Este correo electrónico ya está en uso por otro guía.'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            $guia = Guia::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Guía registrado exitosamente',
                'data' => $guia
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el guía: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateGuia(Request $request, $id)
    {
        $guia = Guia::find($id);

        if (!$guia) {
            return response()->json(['success' => false, 'message' => 'Guía no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            // Validamos que sea único, pero ignorando el ID del guía actual para que pueda guardar sin cambiar la cédula
            'n_cedula' => ['sometimes', 'integer', Rule::unique(Guia::class, 'n_cedula')->ignore($id, 'id_guia')],
            'nombre' => 'sometimes|string|max:255',
            'correo' => ['nullable', 'email', 'max:150', Rule::unique(Guia::class, 'correo')->ignore($id, 'id_guia')],
            'celular' => 'nullable|integer',
            'rnt' => 'nullable|string|max:45',
        ], [
            'n_cedula.unique' => 'El número de cédula ya está ocupado por otro guía.',
            'correo.unique' => 'El correo ya está en uso por otro guía.'
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        try {
            $guia->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Guía actualizado correctamente',
                'data' => $guia
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el guía: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteGuia($id)
    {
        $guia = Guia::find($id);

        if (!$guia) {
            return response()->json(['success' => false, 'message' => 'Guía no encontrado'], 404);
        }

        try {
            $guia->delete();

            return response()->json([
                'success' => true,
                'message' => 'Guía eliminado correctamente'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el guía: ' . $e->getMessage()
            ], 500);
        }
    }
}
