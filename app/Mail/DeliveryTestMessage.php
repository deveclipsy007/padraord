<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeliveryTestMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $sentAt) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Padrão RD · teste de envio');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.delivery-test', with: ['sentAt' => $this->sentAt]);
    }
}
