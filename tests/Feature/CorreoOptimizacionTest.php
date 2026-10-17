<?php

namespace Tests\Feature;

use App\Mail\ActivarCuenta;
use App\Mail\RestablecerPassword;
use App\Services\CorreoService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CorreoOptimizacionTest extends TestCase
{
    public function test_logo_url_retorna_null_en_localhost(): void
    {
        config(['app.url' => 'http://localhost']);
        config(['mail.logo_url' => null]);

        $this->assertNull(CorreoService::logoUrl());
    }

    public function test_logo_url_remota_con_dominio_publico(): void
    {
        config(['app.url' => 'https://cultura.example.com']);
        config(['mail.logo_url' => null]);

        $this->assertSame(
            'https://cultura.example.com/escudoLogo-mail.png',
            CorreoService::logoUrl()
        );
    }

    public function test_logo_url_respeta_override(): void
    {
        $this->assertSame('https://cdn.example.com/logo.png', CorreoService::logoUrl('https://cdn.example.com/logo.png'));
    }

    public function test_existe_imagen_liviana_publica(): void
    {
        $liviana = public_path('escudoLogo-mail.png');
        $pesada = public_path('escudoLogo.png');

        $this->assertFileExists($liviana);
        if (file_exists($pesada)) {
            $this->assertLessThan(filesize($pesada), filesize($liviana));
        }
    }

    public function test_mailable_usa_embed_cuando_no_hay_url_publica(): void
    {
        config(['app.url' => 'http://localhost']);
        config(['mail.logo_url' => null]);

        $mailable = new ActivarCuenta('Ana', 'https://front.test/activar-cuenta?token=abc');

        $this->assertNull($mailable->logoUrl);
        $this->assertNotNull($mailable->logoEmbedPath);

        $html = $mailable->render();

        $this->assertStringContainsString('<img src="', $html);
        // Incrustada: data URI en previsualización, cid: al enviar de verdad.
        $this->assertTrue(str_contains($html, 'cid:') || str_contains($html, 'data:image/'));
    }

    public function test_mailable_usa_remota_con_url_publica(): void
    {
        $mailable = new RestablecerPassword('Ana', 'https://front.test/restablecer-password?token=abc', 'https://cdn.example.com/logo.png');

        $this->assertSame('https://cdn.example.com/logo.png', $mailable->logoUrl);
        $this->assertNull($mailable->logoEmbedPath);

        $html = $mailable->render();

        $this->assertStringContainsString('https://cdn.example.com/logo.png', $html);
        $this->assertStringNotContainsString('cid:', $html);
    }

    public function test_servicio_envia_activacion_sin_adjuntos(): void
    {
        Mail::fake();

        $ok = app(CorreoService::class)->enviarActivacion('a@example.com', 'Ana', 'https://front.test/x?token=abc', 'https://cdn.example.com/logo.png');

        $this->assertTrue($ok);
        Mail::assertSent(ActivarCuenta::class, function (ActivarCuenta $mail) {
            return $mail->hasTo('a@example.com')
                && $mail->logoUrl === 'https://cdn.example.com/logo.png'
                && count($mail->attachments) === 0;
        });
    }

    public function test_servicio_envia_restablecimiento_sin_adjuntos(): void
    {
        Mail::fake();

        $ok = app(CorreoService::class)->enviarRestablecimiento('b@example.com', 'Ana', 'https://front.test/x?token=abc');

        $this->assertTrue($ok);
        Mail::assertSent(RestablecerPassword::class, function (RestablecerPassword $mail) {
            return $mail->hasTo('b@example.com')
                && count($mail->attachments) === 0;
        });
    }
}
