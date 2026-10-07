<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Genera y verifica los tokens de un solo uso para activación
 * de cuentas y recuperación de contraseña (columnas
 * usuario.reset_token_hash / reset_token_expires_at).
 *
 * Solo viaja el token plano por correo; en BD solo vive su hash sha256.
 */
class PasswordTokenService
{
    public function __construct(private int $ttlHoras = 24) {}

    /**
     * @return array{token: string, hash: string, expira_en: DateTimeImmutable}
     */
    public function generar(?int $ttlHoras = null): array
    {
        $token = Str::random(64);

        return [
            'token' => $token,
            'hash' => hash('sha256', $token),
            'expira_en' => new DateTimeImmutable(sprintf('+%d hours', $ttlHoras ?? $this->ttlHoras)),
        ];
    }

    public function verificar(string $tokenPlano, ?string $hashGuardado, mixed $expiraEn, mixed $ahora = null): bool
    {
        if ($tokenPlano === '' || empty($hashGuardado) || empty($expiraEn)) {
            return false;
        }

        $ahoraTs = $this->aTimestamp($ahora ?? new DateTimeImmutable('now'));
        $expiraTs = $this->aTimestamp($expiraEn);

        if ($ahoraTs === null || $expiraTs === null || $ahoraTs > $expiraTs) {
            return false;
        }

        return hash_equals($hashGuardado, hash('sha256', $tokenPlano));
    }

    private function aTimestamp(mixed $valor): ?int
    {
        if ($valor instanceof DateTimeInterface) {
            return $valor->getTimestamp();
        }

        if (is_numeric($valor)) {
            return (int) $valor;
        }

        if (is_string($valor)) {
            $ts = strtotime($valor);

            return $ts === false ? null : $ts;
        }

        return null;
    }
}
