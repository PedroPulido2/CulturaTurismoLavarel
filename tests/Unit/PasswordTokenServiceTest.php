<?php

namespace Tests\Unit;

use App\Services\PasswordTokenService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class PasswordTokenServiceTest extends TestCase
{
    public function test_generar_devuelve_token_hash_y_expiracion_a_24h(): void
    {
        $servicio = new PasswordTokenService(24);

        $resultado = $servicio->generar();

        $this->assertSame(64, strlen($resultado['token']));
        $this->assertSame(hash('sha256', $resultado['token']), $resultado['hash']);

        $esperada = (new DateTimeImmutable('+24 hours'))->getTimestamp();
        $this->assertEqualsWithDelta($esperada, $resultado['expira_en']->getTimestamp(), 5);
    }

    public function test_verificar_acepta_token_vigente(): void
    {
        $servicio = new PasswordTokenService(24);
        $generado = $servicio->generar();

        $this->assertTrue($servicio->verificar(
            $generado['token'],
            $generado['hash'],
            $generado['expira_en'],
            new DateTimeImmutable('now')
        ));
    }

    public function test_verificar_rechaza_token_incorrecto_vencido_o_vacio(): void
    {
        $servicio = new PasswordTokenService(24);
        $generado = $servicio->generar();

        // Token distinto al que generó el hash
        $this->assertFalse($servicio->verificar(
            'otro-token', $generado['hash'], $generado['expira_en'], new DateTimeImmutable('now')
        ));

        // Expirado (ahora posterior a la expiración)
        $this->assertFalse($servicio->verificar(
            $generado['token'], $generado['hash'],
            new DateTimeImmutable('-1 hour'), new DateTimeImmutable('now')
        ));

        // Sin hash guardado (token ya usado) o sin fecha
        $this->assertFalse($servicio->verificar($generado['token'], null, $generado['expira_en']));
        $this->assertFalse($servicio->verificar($generado['token'], $generado['hash'], null));
        $this->assertFalse($servicio->verificar('', $generado['hash'], $generado['expira_en']));
    }
}
