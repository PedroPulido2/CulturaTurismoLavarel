<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Login;
use App\Models\Perfil;
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT;

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

        // Verificacion de la contraseña encriptada
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

        // Actualizar la fecha de ultimo acceso
        $usuario->ultimo_acceso = now();
        $usuario->save();

        // 2. CREACIÓN DEL PAYLOAD (Datos que viajan dentro del token)
        $payload = [
            'iss' => env('APP_URL'), // Emisor (Issuer)
            'aud' => env('APP_URL'), // Audiencia (Audience)
            'iat' => time(), // Emitido en (Issued at)
            'exp' => time() + (60 * 60 * 24), // Expira en (Expiration time) - Aquí son 24 horas
            // Datos útiles para el frontend (no pongas contraseñas aquí)
            'sub' => $perfil->id_perfil,     // Identificador del sujeto
            'user' => $perfil->toArray()
        ];

        // 3. FIRMA DEL TOKEN
        $jwt = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

        return response()->json([
            'success' => true,
            'message' => 'Autenticación exitosa',
            'token' => $jwt
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