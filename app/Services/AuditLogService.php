<?php

namespace App\Services;

use App\Models\AuditoriaLog;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Escritura y lectura de la auditoría.
 *
 * Todo lo sensible se cifra con Crypt::encryptString antes de
 * guardar. Si el cifrado o la inserción fallan, se registra un
 * warning y se devuelve null
 */
class AuditLogService
{
    public function registrar(
        string $modulo,
        string $activity,
        string $estado,
        ?string $detalle = null,
        int|string|null $numDocumento = null,
        ?Request $request = null,
    ): ?AuditoriaLog {
        try {
            $request ??= app('request');

            $payload = [
                'num_documento' => $numDocumento !== null ? (string) $numDocumento : null,
                'activity' => $activity,
                'ip' => $request?->ip(),
                'server_name' => $request?->getHost() ?: $request?->server('SERVER_NAME'),
                'detalle' => $detalle,
            ];

            return AuditoriaLog::create([
                'fecha' => now(),
                'modulo' => $modulo,
                'estado' => $estado,
                'datos_cifrados' => Crypt::encryptString(json_encode($payload, JSON_UNESCAPED_UNICODE)),
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar auditoría: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Variante que resuelve el num_documento desde el id_usuario
     * (el que dejan los middlewares en auth_user_id).
     */
    public function registrarPara(
        int|string|null $idUsuario,
        string $modulo,
        string $activity,
        string $estado,
        ?string $detalle = null,
        ?Request $request = null,
    ): ?AuditoriaLog {
        $numDocumento = null;

        if ($idUsuario !== null) {
            try {
                $numDocumento = Usuario::where('id_usuario', $idUsuario)->value('num_documento');
            } catch (\Throwable $e) {
                Log::warning('No se pudo resolver num_documento para auditoría: '.$e->getMessage());
            }
        }

        return $this->registrar($modulo, $activity, $estado, $detalle, $numDocumento, $request);
    }

    /**
     * Desencripta un registro. Devuelve null si la clave cambió
     * o el payload está corrupto.
     */
    public function desencriptar(AuditoriaLog $log): ?array
    {
        try {
            $datos = json_decode(Crypt::decryptString($log->datos_cifrados), true);

            return is_array($datos) ? $datos : null;
        } catch (\Throwable $e) {
            Log::warning("No se pudo desencriptar auditoría id {$log->id}: ".$e->getMessage());

            return null;
        }
    }
}
