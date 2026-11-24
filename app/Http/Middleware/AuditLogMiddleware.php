<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auditoría automática de escritura.
 *
 * Se ejecuta en terminate() (después de responder) para no sumar
 * latencia. Solo registra POST/PUT/PATCH/DELETE bajo api/* cuya
 * respuesta sea exitosa (2xx): únicamente acciones que de verdad
 * modificaron datos. Los intentos fallidos no se auditan.
 * Excluye login/password (el login exitoso se registra en el
 * controller con el nombre del actor) y los propios endpoints de logs.
 *
 * El detalle es una frase en español: quién (nombre del usuario),
 * qué hizo, sobre qué entidad (id + nombre si la respuesta lo trae)
 * y en qué módulo, más las fotos adjuntas si las hubo.
 */
class AuditLogMiddleware
{
    /**
     * @var array<string, array{0: string, 1: string, 2: string}> [modulo, codigo, frase]
     */
    private const RECURSOS = [
        'tourism' => ['Atractivos Turísticos', 'ATRACTIVO', 'el atractivo turístico'],
        'hotel' => ['Prestadores Servicios', 'HOTEL', 'el hotel'],
        'restaurant' => ['Prestadores Servicios', 'RESTAURANTE', 'el restaurante'],
        'agency' => ['Prestadores Servicios', 'AGENCIA', 'la agencia'],
        'guide' => ['Prestadores Servicios', 'GUIA', 'el guía'],
        'tipos-cocina' => ['Prestadores Servicios', 'TIPO_COCINA', 'el tipo de cocina'],
        'tipos-agencia' => ['Prestadores Servicios', 'TIPO_AGENCIA', 'el tipo de agencia'],
        'especialidades' => ['Prestadores Servicios', 'ESPECIALIDAD', 'la especialidad'],
        'disponibilidades' => ['Prestadores Servicios', 'DISPONIBILIDAD', 'la disponibilidad'],
        'tipos-publico' => ['Prestadores Servicios', 'TIPO_PUBLICO', 'el tipo de público'],
        'cultural-services' => ['Servicios Culturales', 'SERVICIO_CULTURAL', 'el servicio cultural'],
        'areas-artisticas' => ['Servicios Culturales', 'AREA_ARTISTICA', 'el área artística'],
        'tipos-servicio' => ['Servicios Culturales', 'TIPO_SERVICIO', 'el tipo de servicio'],
        'publico-dirigido' => ['Servicios Culturales', 'PUBLICO_DIRIGIDO', 'el público dirigido'],
        'event' => ['Eventos', 'EVENTO', 'el evento'],
        'archivos' => ['Gestor Archivos', 'ARCHIVO', 'el archivo'],
        'carpetas' => ['Gestor Archivos', 'CARPETA', 'la carpeta'],
        'profiles' => ['Perfiles', 'PERFIL', 'el perfil'],
    ];

    public function __construct(private AuditLogService $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                return;
            }

            $ruta = trim($request->path(), '/');

            if (! str_starts_with($ruta, 'api/')) {
                return;
            }

            $relativa = substr($ruta, 4);

            foreach (['login', 'password', 'logs'] as $excluida) {
                if ($relativa === $excluida || str_starts_with($relativa, $excluida.'/')) {
                    return;
                }
            }

            $inferido = $this->inferir($request->method(), $relativa);

            if ($inferido === null) {
                return;
            }

            $status = $response->getStatusCode();

            // Solo acciones que lograron modificar los datos.
            if ($status < 200 || $status >= 300) {
                return;
            }

            [$modulo, $actividad] = $inferido;

            $this->audit->registrarPara(
                $request->attributes->get('auth_user_id'),
                $modulo,
                $actividad,
                'OK',
                $this->construirDetalle($request, $response, $modulo, $actividad),
                $request,
            );
        } catch (\Throwable $e) {
            Log::warning('AuditLogMiddleware: '.$e->getMessage());
        }
    }

    /**
     * @return array{0: string, 1: string}|null [modulo, actividad]
     */
    private function inferir(string $metodo, string $relativa): ?array
    {
        $segmentos = explode('/', $relativa);
        $base = $segmentos[0] ?? '';

        if (! isset(self::RECURSOS[$base])) {
            return null;
        }

        [$modulo, $codigo] = self::RECURSOS[$base];

        // Permisos sobre perfiles.
        if ($base === 'profiles' && in_array('modulos', $segmentos, true)) {
            return $metodo === 'DELETE'
                ? [$modulo, 'REVOCAR_PERMISO']
                : [$modulo, 'OTORGAR_PERMISO'];
        }

        if ($base === 'profiles') {
            return match ($metodo) {
                'POST' => [$modulo, 'CREAR_PERFIL'],
                'PUT' => [$modulo, 'EDITAR_PERFIL'],
                'DELETE' => [$modulo, 'ELIMINAR_PERFIL'],
                default => [$modulo, 'GESTIONAR_PERFIL'],
            };
        }

        if ($metodo === 'PATCH' && in_array('visibility', $segmentos, true)) {
            return [$modulo, "CAMBIAR_VISIBILIDAD_{$codigo}"];
        }

        if ($base === 'event' && $metodo === 'PATCH' && in_array('cancel', $segmentos, true)) {
            return [$modulo, 'CANCELAR_EVENTO'];
        }

        return match ($metodo) {
            'POST' => [$modulo, "CREAR_{$codigo}"],
            'PUT' => [$modulo, "EDITAR_{$codigo}"],
            'DELETE' => [$modulo, "ELIMINAR_{$codigo}"],
            'PATCH' => [$modulo, "ACTUALIZAR_{$codigo}"],
            default => [$modulo, "GESTIONAR_{$codigo}"],
        };
    }

    private function construirDetalle(Request $request, Response $response, string $modulo, string $actividad): string
    {
        $actor = $this->actor($request);

        $especial = match ($actividad) {
            'OTORGAR_PERMISO' => $this->detalleOtorgar($request, $actor),
            'REVOCAR_PERMISO' => $this->detalleRevocar($request, $actor),
            'CREAR_PERFIL' => $this->detalleCrearPerfil($request, $actor),
            'CANCELAR_EVENTO' => $this->detalleGenerico($request, $response, $actor, $modulo, 'canceló', 'el evento'),
            default => null,
        };

        if ($especial !== null) {
            return $especial;
        }

        if (str_starts_with($actividad, 'CAMBIAR_VISIBILIDAD_')) {
            $recurso = $this->fraseRecurso($request);
            $verbo = $request->boolean('is_visible') ? 'hizo visible' : 'ocultó';

            return $this->detalleGenerico($request, $response, $actor, $modulo, $verbo, $recurso);
        }

        $verbo = match ($request->method()) {
            'POST' => 'registró',
            'PUT' => 'editó',
            'DELETE' => 'eliminó',
            default => 'actualizó',
        };

        return $this->detalleGenerico($request, $response, $actor, $modulo, $verbo, $this->fraseRecurso($request));
    }

    private function detalleGenerico(
        Request $request,
        Response $response,
        string $actor,
        string $modulo,
        string $verbo,
        string $recurso,
    ): string {
        $texto = "{$actor} {$verbo} {$recurso}";

        $id = $this->identificador($request) ?? $this->identificadorRespuesta($response);

        if ($id !== null) {
            $texto .= " con id {$id}";
        }

        $nombre = $this->nombreEntidad($response);

        if ($nombre !== null) {
            $texto .= " ({$nombre})";
        }

        return $texto." en el módulo {$modulo}.".$this->adjuntoFotos($request);
    }

    private function detalleOtorgar(Request $request, string $actor): string
    {
        $ids = implode(', ', (array) $request->input('modulo_ids', []));
        $usuario = $this->identificador($request) ?? 'desconocido';

        return "{$actor} otorgó permiso de los módulos [{$ids}] al usuario con id {$usuario} en el módulo Perfiles.";
    }

    private function detalleRevocar(Request $request, string $actor): string
    {
        $parametros = $request->route()?->parameters() ?? [];
        $usuario = isset($parametros['id_usuario']) && is_scalar($parametros['id_usuario'])
            ? (string) $parametros['id_usuario']
            : 'desconocido';
        $moduloRevocado = isset($parametros['id_modulo']) && is_scalar($parametros['id_modulo'])
            ? (string) $parametros['id_modulo']
            : 'desconocido';

        return "{$actor} revocó el permiso del módulo {$moduloRevocado} al usuario con id {$usuario} en el módulo Perfiles.";
    }

    private function detalleCrearPerfil(Request $request, string $actor): string
    {
        $nombre = trim($request->input('nombre', '').' '.$request->input('apellido', ''));
        $correo = $request->input('correo', '');

        $texto = "{$actor} registró el perfil de ".($nombre !== '' ? $nombre : 'un usuario');

        if (is_string($correo) && $correo !== '') {
            $texto .= " ({$correo})";
        }

        return $texto.' en el módulo Perfiles.'.$this->adjuntoFotos($request);
    }

    private function fraseRecurso(Request $request): string
    {
        $segmentos = explode('/', trim($request->path(), '/'));
        // Ruta con forma api/{base}/...
        $base = $segmentos[1] ?? '';

        return self::RECURSOS[$base][2] ?? 'el registro';
    }

    private function actor(Request $request): string
    {
        $id = $request->attributes->get('auth_user_id');

        if ($id === null) {
            return 'Un usuario';
        }

        try {
            $usuario = Usuario::where('id_usuario', $id)->first(['nombre', 'apellido']);
        } catch (\Throwable $e) {
            return 'Un usuario';
        }

        if (! $usuario) {
            return 'Un usuario';
        }

        $nombre = trim("{$usuario->nombre} {$usuario->apellido}");

        return $nombre !== '' ? $nombre : 'Un usuario';
    }

    /**
     * Primer parámetro de ruta escalar (id de entidad, usuario o módulo).
     */
    private function identificador(Request $request): ?string
    {
        $parametros = $request->route()?->parameters() ?? [];

        foreach ($parametros as $valor) {
            if (is_scalar($valor)) {
                return (string) $valor;
            }
        }

        return null;
    }

    /**
     * Lee el cuerpo JSON de la respuesta una sola vez.
     *
     * @return array<string, mixed>|null
     */
    private function datosRespuesta(Response $response): ?array
    {
        try {
            $decodificado = json_decode($response->getContent(), true);
        } catch (\Throwable $e) {
            return null;
        }

        if (! is_array($decodificado)) {
            return null;
        }

        $datos = $decodificado['data'] ?? null;

        if (is_array($datos) && isset($datos['data']) && is_array($datos['data'])) {
            $datos = $datos['data'];
        }

        return is_array($datos) ? $datos : null;
    }

    /**
     * Busca un id en la respuesta (PK que el front envió o devolvió
     * el controller) cuando la ruta no trae parámetro.
     */
    private function identificadorRespuesta(Response $response): ?string
    {
        $datos = $this->datosRespuesta($response);

        if ($datos === null) {
            return null;
        }

        foreach ($datos as $clave => $valor) {
            if (is_scalar($valor) && ($clave === 'id' || str_starts_with((string) $clave, 'id_'))) {
                return (string) $valor;
            }
        }

        return null;
    }

    private function nombreEntidad(Response $response): ?string
    {
        $datos = $this->datosRespuesta($response);

        if ($datos === null) {
            return null;
        }

        foreach (['nombre', 'titulo', 'title', 'name'] as $clave) {
            if (isset($datos[$clave]) && is_scalar($datos[$clave]) && (string) $datos[$clave] !== '') {
                return (string) $datos[$clave];
            }
        }

        return null;
    }

    /**
     * " Adjuntó 2 fotos." o "" si no hubo archivos.
     */
    private function adjuntoFotos(Request $request): string
    {
        $total = 0;

        foreach (['fotos', 'url_foto', 'archivo'] as $clave) {
            if (! $request->hasFile($clave)) {
                continue;
            }

            $archivos = $request->file($clave);
            $total += is_array($archivos) ? count($archivos) : 1;
        }

        if ($total === 0) {
            return '';
        }

        return $total === 1 ? ' Adjuntó 1 foto.' : " Adjuntó {$total} fotos.";
    }
}
