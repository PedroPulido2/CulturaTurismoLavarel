<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Verifica el JWT (Bearer) y exige rol "Super Administrador".
     * Deja el id del otorgante en $request->attributes->get('auth_user_id').
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['success' => false, 'message' => 'Token no proporcionado'], 401);
        }

        try {
            $payload = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Token inválido o expirado'], 401);
        }

        $usuario = Usuario::find($payload->sub ?? null);

        if (! $usuario || ! $usuario->esSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Acceso denegado. Se requiere Super Administrador'], 403);
        }

        $request->attributes->set('auth_user_id', $usuario->id_usuario);

        return $next($request);
    }
}
