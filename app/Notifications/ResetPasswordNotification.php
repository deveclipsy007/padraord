<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::to(route('password.reset', ['token' => $this->token], false).'?email='.urlencode($notifiable->getEmailForPasswordReset()));
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Padrão RD · redefinição de senha')
            ->greeting('Olá, '.$notifiable->name.'.')
            ->line('Recebemos um pedido para redefinir a senha do seu acesso ao Padrão RD.')
            ->action('Definir nova senha', $url)
            ->line("Este link vale por {$minutes} minutos e só pode ser usado uma vez.")
            ->line('Se não foi você quem pediu, ignore esta mensagem: sua senha atual continua valendo.')
            ->salutation('Equipe Padrão RD');
    }
}
