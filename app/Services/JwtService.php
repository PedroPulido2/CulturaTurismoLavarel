<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Emisión y verificación centralizada del JWT HS256 de la API.
 *
 * El payload es mínimo (sub/iss/aud/iat/exp): nunca incluir PII
 * ni el usuario completo dentro del token.
 */
class JwtService
{
    public function __construct(
        private ?string $secret = null,
        private int $ttlHoras = 24,
    ) {}

    private function resolvedSecret(): string
    {
        $secret = $this->secret ?? (string) env('JWT_SECRET', '');

        if ($secret === '') {
            throw new \RuntimeException('JWT_SECRET no configurado');
        }

        return $secret;
    }

    private function issuer(): string
    {
        return (string) (env('APP_URL', config('app.url', 'http://localhost')));
    }

    /**
     * Emite un token para el usuario dado.
     */
    public function issueToken(int $idUsuario, ?int $ttlHoras = null): string
    {
        $ahora = time();
        $ttl = ($ttlHoras ?? $this->ttlHoras) * 60 * 60;
        $emisor = $this->issuer();

        $payload = [
            'iss' => $emisor,
            'aud' => $emisor,
            'iat' => $ahora,
            'exp' => $ahora + $ttl,
            'sub' => $idUsuario,
        ];

        return JWT::encode($payload, $this->resolvedSecret(), 'HS256');
    }

    /**
     * Verifica firma + expiración y devuelve el payload.
     *
     * @throws \Exception si el token es inválido o expiró.
     */
    public function decode(string $token): object
    {
        return JWT::decode($token, new Key($this->resolvedSecret(), 'HS256'));
    }

    /**
     * Atajo: verifica y devuelve el id de usuario (sub).
     *
     * @throws \Exception
     */
    public function parseUserId(string $token): int
    {
        $payload = $this->decode($token);

        return (int) ($payload->sub ?? 0);
    }
}
