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
     * Retorna null cuando no hay URL pública utilizable (ej. APP_URL en
     * localhost, que ningún cliente de correo puede resolver): en ese caso
     * las plantillas incrustan el archivo (embed), que siempre se muestra.
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

        $base = rtrim((string) config('app.url', 'http://localhost'), '/');
        $url = $base.'/escudoLogo-mail.png';

        if (! self::esHostPublico($url)) {
            return null;
        }

        if (! is_file(public_path('escudoLogo-mail.png'))) {
            return null;
        }

        return $url;
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

    private static function esHostPublico(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '' || $host === 'localhost') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        // Nombres de un solo segmento (mailpit, app, ...) son red local.
        if (! str_contains($host, '.')) {
            return false;
        }

        return true;
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
