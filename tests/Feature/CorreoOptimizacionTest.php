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

    public function test_logo_url_prefiere_embed_aunque_dominio_sea_publico(): void
    {
        // APP_URL puede apuntar al frontend (ej. Vercel) y ese host no sirve
        // los assets de public/ del backend (404 -> imagen rota). Por eso,
        // sin MAIL_LOGO_URL explícito siempre se incrusta (embed CID).
        config(['app.url' => 'https://cultura.example.com']);
        config(['mail.logo_url' => null]);

        $this->assertNull(CorreoService::logoUrl());
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

        // La plantilla actual no incluye imagen: solo texto institucional.
        $this->assertStringContainsString('Activar mi cuenta', $html);
        $this->assertStringContainsString('https://front.test/activar-cuenta?token=abc', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_mailable_ignora_logo_remoto_sin_imagen_en_plantilla(): void
    {
        $mailable = new RestablecerPassword('Ana', 'https://front.test/restablecer-password?token=abc', 'https://cdn.example.com/logo.png');

        $this->assertSame('https://cdn.example.com/logo.png', $mailable->logoUrl);
        $this->assertNull($mailable->logoEmbedPath);

        $html = $mailable->render();

        $this->assertStringContainsString('Restablecer', $html);
        $this->assertStringNotContainsString('<img', $html);
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
