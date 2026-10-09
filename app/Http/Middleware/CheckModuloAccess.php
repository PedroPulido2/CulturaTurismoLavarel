<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige JWT válido + cuenta ACTIVO + (Super Administrador
 * o permiso explícito en uno de los módulos indicados).
 *
 * Uso: ->middleware('modulo:Eventos')
 *      ->middleware('modulo:Prestadores Servicios')
 *      ->middleware('modulo:Atractivos Turísticos,Eventos')
 *
 * Los nombres deben coincidir con culturayturismo.modulo.nombre.
 */
class CheckModuloAccess
{
    public function __construct(private JwtService $jwt) {}

    public function handle(Request $request, Closure $next, string ...$modulos): Response
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

        $usuario = Usuario::with(['rol', 'login', 'modulos'])->find($payload->sub ?? null);

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

        $permitidos = $usuario->modulos->pluck('nombre')->all();

        foreach ($modulos as $requerido) {
            if (in_array(trim($requerido), $permitidos, true)) {
                $request->attributes->set('auth_modulos', $permitidos);

                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Acceso denegado. Se requiere permiso del módulo: '.implode(', ', $modulos),
        ], 403);
    }
}
