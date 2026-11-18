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
     * URL pública absoluta del logo liviano (public/escudoLogo-mail.png).
     * Por defecto retorna null para que las plantillas incrusten el archivo
     * (embed CID), que siempre se muestra: la URL armada con APP_URL no es
     * confiable porque APP_URL apunta al frontend (Vercel) y ese host no
     * sirve los assets de public/ del backend (devuelve 404 -> imagen rota).
     * Solo se usa URL remota si se configura explícitamente vía
     * MAIL_LOGO_URL o por override.
     */
    public static function logoUrl(?string $override = null): ?string
    {
        if (is_string($override) && $override !== '') {
            return $override;
        }

        $directa = config('mail.logo_url');
        if (is_string($directa) && $directa !== '') {
            return $directa;
        }

        return null;
    }

    /**
     * Ruta local del logo a incrustar cuando no hay URL remota usable.
     * Prefiere la versión liviana y cae a la original.
     */
    public static function logoEmbedPath(): ?string
    {
        foreach (['escudoLogo-mail.png', 'escudoLogo.png'] as $archivo) {
            $ruta = public_path($archivo);
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
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
