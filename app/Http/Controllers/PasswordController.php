<?php

namespace App\Http\Controllers;

use App\Mail\ActivarCuenta;
use App\Mail\RestablecerPassword;
use App\Models\Login;
use App\Models\Usuario;
use App\Services\PasswordTokenService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PasswordController extends Controller
{
    public function __construct(private PasswordTokenService $tokens) {}

    /**
     * Activa una cuenta nueva: el usuario define su contraseña con el token del correo.
     */
    public function activar(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        $usuario = $this->buscarPorToken($request->token);

        if (! $usuario) {
            return response()->json(['success' => false, 'message' => 'El enlace es inválido o expiró'], 400);
        }

        $login = Login::where('id_usuario', $usuario->id_usuario)->first();

        if (! $login) {
            return response()->json(['success' => false, 'message' => 'El enlace es inválido o expiró'], 400);
        }

        try {
            DB::beginTransaction();

            $login->password = Hash::make($request->password);
            $login->estado = 'ACTIVO';
            $login->intentos_fallidos = 0;
            $login->save();

            // Token de un solo uso: se limpia al activarse
            $usuario->reset_token_hash = null;
            $usuario->reset_token_expires_at = null;
            $usuario->save();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Cuenta activada correctamente. Ya puedes iniciar sesión']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error activando cuenta: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'No se pudo activar la cuenta'], 500);
        }
    }

    /**
     * Solicita el correo de recuperación. Respuesta siempre genérica
     * para no revelar qué correos están registrados.
     */
    public function olvide(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'correo' => 'required|email',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        $usuario = Usuario::where('correo', $request->correo)->first();
        $login = $usuario ? Login::where('id_usuario', $usuario->id_usuario)->first() : null;

        if ($usuario && $login && $login->estado !== 'ELIMINADO') {
            try {
                $token = $this->emitirToken($usuario);
                $url = $this->urlFrontend(env('FRONTEND_RESET_PATH', '/restablecer-password'), $token);
                Mail::to($usuario->correo)->send(new RestablecerPassword($usuario->nombre, $url));
            } catch (Exception $e) {
                Log::error('Error enviando correo de recuperación: '.$e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Si el correo está registrado, recibirás instrucciones para restablecer tu contraseña',
        ]);
    }

    /**
     * Define la nueva contraseña con el token del correo.
     * Si la cuenta estaba BLOQUEADA por intentos fallidos, se desbloquea.
     */
    public function restablecer(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        $usuario = $this->buscarPorToken($request->token);

        if (! $usuario) {
            return response()->json(['success' => false, 'message' => 'El enlace es inválido o expiró'], 400);
        }

        $login = Login::where('id_usuario', $usuario->id_usuario)->first();

        if (! $login) {
            return response()->json(['success' => false, 'message' => 'El enlace es inválido o expiró'], 400);
        }

        try {
            DB::beginTransaction();

            $login->password = Hash::make($request->password);
            $login->intentos_fallidos = 0;
            if ($login->estado === 'BLOQUEADO') {
                $login->estado = 'ACTIVO';
            }
            $login->save();

            $usuario->reset_token_hash = null;
            $usuario->reset_token_expires_at = null;
            $usuario->save();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Contraseña restablecida correctamente. Ya puedes iniciar sesión']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error restableciendo contraseña: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'No se pudo restablecer la contraseña'], 500);
        }
    }

    /**
     * Reenvía el correo de activación (solo cuentas aún pendientes).
     */
    public function reenviarActivacion(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'correo' => 'required|email',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        $usuario = Usuario::where('correo', $request->correo)->first();
        $login = $usuario ? Login::where('id_usuario', $usuario->id_usuario)->first() : null;

        if ($usuario && $login && $login->estado === 'EN_VERIFICACION') {
            try {
                $token = $this->emitirToken($usuario);
                $url = $this->urlFrontend(env('FRONTEND_ACTIVATION_PATH', '/activar-cuenta'), $token);
                Mail::to($usuario->correo)->send(new ActivarCuenta($usuario->nombre, $url));
            } catch (Exception $e) {
                Log::error('Error reenviando activación: '.$e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Si la cuenta está pendiente de activación, se reenvió el correo',
        ]);
    }

    private function buscarPorToken(string $tokenPlano): ?Usuario
    {
        $usuario = Usuario::where('reset_token_hash', hash('sha256', $tokenPlano))->first();

        if (! $usuario) {
            return null;
        }

        if (! $this->tokens->verificar($tokenPlano, $usuario->reset_token_hash, $usuario->reset_token_expires_at)) {
            return null;
        }

        return $usuario;
    }

    private function emitirToken(Usuario $usuario): string
    {
        $generado = $this->tokens->generar();

        $usuario->reset_token_hash = $generado['hash'];
        $usuario->reset_token_expires_at = $generado['expira_en'];
        $usuario->save();

        return $generado['token'];
    }

    private function urlFrontend(string $ruta, string $token): string
    {
        $base = rtrim(env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/');

        return $base.'/'.ltrim($ruta, '/').'?token='.$token;
    }
}
