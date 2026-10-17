<?php

namespace App\Mail;

use App\Services\CorreoService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ActivarCuenta extends Mailable
{
    use Queueable, SerializesModels;

    public string $logoUrl;

    public function __construct(
        public string $nombre,
        public string $url,
        ?string $logoUrl = null,
    ) {
        $this->logoUrl = $logoUrl ?? CorreoService::logoUrl();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Activa tu cuenta — Turismo Sogamoso',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.activar-cuenta',
        );
    }
}
