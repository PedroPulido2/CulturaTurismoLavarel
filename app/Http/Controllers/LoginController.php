<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Login;
use App\Models\Perfil;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{

    public function login(Request $request)
    {
        $perfil = Perfil::where("correo", $request->correo)->first();

        if (!$perfil) {
            return response()->json(['success' => false, 'message' => 'Usuario o contraseña incorrectos'], 401);
        }
    
        $usuario = Login::where('id_perfil', $perfil->id_perfil)->first();

        if (!$usuario) {
            return response()->json(['success'=> false, 'message'=> 'Error de integridad. Credenciales no encontradas'],403); 
        }

        if ($usuario->estado !== 'ACTIVO') {
            return response()->json(['success' => false, 'message' => 'Usuario inactivo o bloqueado. Contacte con soporte'], 403);
        }

        //Verificacion de la contraseña encriptada
        if (!Hash::check($request->password, $usuario->password)) {
            $usuario->intentos_fallidos += 1;

            if ($usuario->intentos_fallidos >= 5) {
                $usuario->estado = 'BLOQUEADO';
                $usuario->save();

                return response()->json(['success' => false, 'message' => 'Cuenta Bloqueada por multiples intentos fallidos'], 403);
            }
            $usuario->save();
            return response()->json(['success' => false, 'message' => 'Usuario o contraseña incorrectos'], 401);
        }

        if ($usuario->intentos_fallidos > 0) {
            $usuario->intentos_fallidos = 0;
            $usuario->save();
        }

        //Actualizar la fecha de ultimo acceso
        $usuario->ultimo_acceso = now();
        $usuario->save();

        $usuario->perfil = $perfil;
        return response()->json([
            'success' => true,
            'message' => 'Autenticación exitoso',
            'data' => $usuario
        ]);
    }

    public function unlockUser(Request $request, $id_perfil)
    {
        $usuario = Login::where('id_perfil', $id_perfil)->first();

        if (!$usuario) {
            return response()->json(['success' => false, 'message' => 'Perfil no encontrado'], 404);
        }

        $usuario->estado = 'ACTIVO';
        $usuario->intentos_fallidos = 0;
        $usuario->save();

        return response()->json(['success' => true, 'message' => 'Usuario desbloqueado exitosamente'], 200);
    }
}