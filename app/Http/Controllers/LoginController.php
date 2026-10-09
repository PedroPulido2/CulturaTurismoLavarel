<?php

namespace App\Http\Controllers;

use App\Models\Login;
use App\Models\Usuario;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function __construct(private JwtService $jwt) {}

    public function login(Request $request)
    {
        $usuario_data = Usuario::where('correo', $request->correo)->first();

        if (! $usuario_data) {
            return response()->json(['success' => false, 'message' => 'Usuario o contraseña incorrectos'], 401);
        }

        $usuario = Login::where('id_usuario', $usuario_data->id_usuario)->first();

        if (! $usuario) {
            return response()->json(['success' => false, 'message' => 'Error de integridad. Credenciales no encontradas'], 403);
        }

        if ($usuario->estado === 'EN_VERIFICACION') {
            return response()->json(['success' => false, 'message' => 'Cuenta pendiente de activación. Revisa tu correo electrónico'], 403);
        }

        if ($usuario->estado !== 'ACTIVO') {
            return response()->json(['success' => false, 'message' => 'Usuario inactivo o bloqueado. Contacte con soporte'], 403);
        }

        // Verificacion de la contraseña encriptada
        if (! Hash::check($request->password, $usuario->password)) {
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

        // Token mínimo (sub/iss/aud/iat/exp). El frontend pide
        // GET /api/profiles/{id} para los datos del usuario.
        $jwt = $this->jwt->issueToken($usuario_data->id_usuario);

        return response()->json([
            'success' => true,
            'message' => 'Autenticación exitosa',
            'token' => $jwt,
        ]);
    }

    public function unlockUser(Request $request, $id_usuario)
    {
        $usuario = Login::where('id_usuario', $id_usuario)->first();

        if (! $usuario) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        $usuario->estado = 'ACTIVO';
        $usuario->intentos_fallidos = 0;
        $usuario->save();

        return response()->json(['success' => true, 'message' => 'Usuario desbloqueado exitosamente'], 200);
    }
}
