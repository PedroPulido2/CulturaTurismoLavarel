<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solo exige login válido (cualquier rol) con cuenta ACTIVO.
 * Deja auth_user_id / auth_is_superadmin en los attributes.
 */
class AuthenticateJwt
{
    public function __construct(private JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['success' => false, 'message' => 'Token no proporcionado'], 401);
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Token inválido o expirado'], 401);
        }

        $usuario = Usuario::with(['rol', 'login'])->find($payload->sub ?? null);

        if (! $usuario) {
            return response()->json(['success' => false, 'message' => 'Token inválido o expirado'], 401);
        }

        if (($usuario->login?->estado ?? null) !== 'ACTIVO') {
            return response()->json(['success' => false, 'message' => 'Usuario inactivo o bloqueado. Contacte con soporte'], 403);
        }

        $request->attributes->set('auth_user_id', $usuario->id_usuario);
        $request->attributes->set('auth_is_superadmin', $usuario->esSuperAdmin());

        return $next($request);
    }
}
