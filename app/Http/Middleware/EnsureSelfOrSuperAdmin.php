<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permite ver/editar un perfil al Super Administrador
 * o al propio dueño de la cuenta.
 *
 * Soporta rutas con {id_usuario} o con {email}:
 *   ->middleware('self_or_superadmin:id_usuario')
 *   ->middleware('self_or_superadmin:email')
 */
class EnsureSelfOrSuperAdmin
{
    public function __construct(private JwtService $jwt) {}

    public function handle(Request $request, Closure $next, string $parametro = 'id_usuario'): Response
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

        $esSuper = $usuario->esSuperAdmin();
        $request->attributes->set('auth_user_id', $usuario->id_usuario);
        $request->attributes->set('auth_is_superadmin', $esSuper);

        if ($esSuper) {
            return $next($request);
        }

        $objetivo = $request->route($parametro);

        $esPropio = $parametro === 'email'
            ? (strtolower((string) $objetivo) === strtolower((string) $usuario->correo))
            : ((string) $objetivo === (string) $usuario->id_usuario);

        if (! $esPropio) {
            return response()->json(['success' => false, 'message' => 'Acceso denegado. Solo puedes ver tu propio perfil'], 403);
        }

        return $next($request);
    }
}
