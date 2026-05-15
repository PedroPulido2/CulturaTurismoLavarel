<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Perfil;
use App\Models\Login;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function getAllProfiles()
    {
        // Lógica para mostrar una lista de usuarios
        $perfiles = Perfil::all();
        return response()->json(['success' => true, 'data' => $perfiles]);
    }

    public function getProfileByEmail($email)
    {
        // Lógica para mostrar un usuario específico por correo electrónico
        $perfil = Perfil::where('correo', $email)->first();
        if (!$perfil) {
            return response()->json(['success' => false, 'message' => 'Perfil no encontrado'], 404);
        }
        return response()->json(['success' => true, 'data' => $perfil]);
    }

    public function getPRofileById($id_perfil)
    {
        $perfil = Perfil::where('id_perfil', $id_perfil)->first();

        if (!$perfil) {
            return response()->json(['success' => false, 'message' => 'Perfil no encontrado'], 404);
        }
        return response()->json(['success' => true, 'data' => $perfil]);
    }

    public function createProfile(Request $request)
    {
        $reglas = [
            'id_perfil' => [
                'required', 
                Rule::unique(Perfil::class, 'id_perfil')
            ],
            'correo' => [
                'required', 
                'email', 
                Rule::unique(Perfil::class, 'correo')
            ]
        ];

        $mensajes = [
            'id_perfil.unique' => 'Este ID / Número de identificación ya se encuentra registrado.',
            'correo.unique' => 'Este correo electrónico ya está en uso por otra cuenta.'
        ];

        $validador = Validator::make($request->all(), $reglas, $mensajes);

        // Si la validación falla, devolvemos un error 400 antes de intentar guardar nada
        if ($validador->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validador->errors()
            ], 400);
        }

        // Lógica para crear un nuevo usuario
        $perfil = Perfil::create([
            'id_perfil' => $request->id_perfil,
            'nombre' => $request->nombre,
            'apellido' => $request->apellido,
            'correo' => $request->correo,
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'genero' => $request->genero,
            'telefono' => $request->telefono,
            'tipo_identificacion' => $request->tipo_identificacion,
        ]);

        $login = Login::create([
            'id_perfil' => $perfil->id_perfil,
            'password' => Hash::make($request->password),
            'estado' => 'ACTIVO',
            'intentos_fallidos' => 0,
        ]);


        return response()->json([
            'success' => true,
            'message' => 'Perfil creado exitosamente',
            'data' => $perfil
        ], 201);
    }

    public function updateProfile(Request $request, $id)
    {
        // Lógica para actualizar un usuario existente
        $perfil = Perfil::find($id);

        if (!$perfil) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil no encontrado',
            ], 404);
        }

        $reglas = [
            'id_perfil' => [
                'required', 
                // Valida que sea único, pero ignora el ID del perfil que estamos editando
                Rule::unique(Perfil::class, 'id_perfil')->ignore($id, 'id_perfil')
            ],
            'correo' => [
                'required', 
                'email', 
                // Valida que sea único, pero ignora el correo del perfil que estamos editando
                Rule::unique(Perfil::class, 'correo')->ignore($id, 'id_perfil')
            ]
        ];

        $mensajes = [
            'id_perfil.unique' => 'Este ID / Número de identificación ya está ocupado por otra persona.',
            'correo.unique' => 'Este correo electrónico ya está en uso por otra persona.'
        ];

        $validador = Validator::make($request->all(), $reglas, $mensajes);


        if ($validador->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validador->errors()
            ], 400);
        }

        $perfil->update($request->all());

        Login::where('id_perfil', $id)->update(['password' => Hash::make($request->password)]);

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado exitosamente',
            'data' => $perfil
        ]);

    }

    public function deleteProfile($id)
    {
        $perfil = Perfil::find($id);

        if (!$perfil) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil no encontrado'
            ], 404);
        }

        $perfil->delete();

        return response()->json([
            'success' => true,
            'message' => 'Perfil eliminado correctamente'
        ]);
    }
}
