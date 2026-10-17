<?php

namespace App\Services;

use App\Mail\ActivarCuenta;
use App\Mail\RestablecerPassword;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CorreoService
{
    /**
     * URL pública absoluta del logo liviano para los correos.
     * Se sirve desde public/escudoLogo-mail.png (imagen remota:
     * se ve en el diseño pero no viaja como adjunto).
     */
    public static function logoUrl(?string $override = null): string
    {
        if ($override) {
            return $override;
        }

        $directa = env('MAIL_LOGO_URL');
        if (is_string($directa) && $directa !== '') {
            return $directa;
        }

        $base = rtrim((string) config('app.url', env('APP_URL', 'http://localhost')), '/');

        return $base.'/escudoLogo-mail.png';
    }

    public function enviarActivacion(string $correo, string $nombre, string $url, ?string $logoUrl = null): bool
    {
        return $this->enviar(new ActivarCuenta($nombre, $url, $logoUrl), $correo, 'activación');
    }

    public function enviarRestablecimiento(string $correo, string $nombre, string $url, ?string $logoUrl = null): bool
    {
        return $this->enviar(new RestablecerPassword($nombre, $url, $logoUrl), $correo, 'recuperación');
    }

    private function enviar(ActivarCuenta|RestablecerPassword $mailable, string $correo, string $tipo): bool
    {
        $inicio = microtime(true);
        try {
            Mail::to($correo)->send($mailable);
            Log::info('Correo de '.$tipo.' enviado a '.$correo.' en '.round((microtime(true) - $inicio) * 1000).'ms');

            return true;
        } catch (Exception $e) {
            Log::error('Error enviando correo de '.$tipo.' a '.$correo.': '.$e->getMessage());

            return false;
        }
    }
}
