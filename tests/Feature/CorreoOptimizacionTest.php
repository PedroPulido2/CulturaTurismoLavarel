<?php

namespace Tests\Feature;

use App\Mail\ActivarCuenta;
use App\Mail\RestablecerPassword;
use App\Services\CorreoService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CorreoOptimizacionTest extends TestCase
{
    public function test_logo_url_es_remota_y_liviana(): void
    {
        $url = CorreoService::logoUrl();

        $this->assertStringEndsWith('/escudoLogo-mail.png', $url);
        $this->assertMatchesRegularExpression('#^https?://#', $url);
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

    public function test_mailable_activacion_expone_logo_sin_embed(): void
    {
        $mailable = new ActivarCuenta('Ana', 'https://front.test/activar-cuenta?token=abc');

        $this->assertStringEndsWith('/escudoLogo-mail.png', $mailable->logoUrl);

        $html = $mailable->render();

        $this->assertStringContainsString('escudoLogo-mail.png', $html);
        $this->assertStringNotContainsString('cid:', $html);
        $this->assertStringContainsString('<img src="http', $html);
    }

    public function test_mailable_restablecer_expone_logo_sin_embed(): void
    {
        $mailable = new RestablecerPassword('Ana', 'https://front.test/restablecer-password?token=abc');

        $html = $mailable->render();

        $this->assertStringContainsString('escudoLogo-mail.png', $html);
        $this->assertStringNotContainsString('cid:', $html);
    }

    public function test_servicio_envia_activacion_sin_adjuntos(): void
    {
        Mail::fake();

        $ok = app(CorreoService::class)->enviarActivacion('a@example.com', 'Ana', 'https://front.test/x?token=abc');

        $this->assertTrue($ok);
        Mail::assertSent(ActivarCuenta::class, function (ActivarCuenta $mail) {
            return $mail->hasTo('a@example.com')
                && str_ends_with($mail->logoUrl, '/escudoLogo-mail.png')
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
