<?php

namespace Tests\Feature;

use App\Mail\DeliveryTestMessage;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function smtpConfigured(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'sistema@padraord.com.br',
            'mail.from.name' => 'Padrão RD',
            'mail.mailers.smtp.host' => 'smtp.hostinger.com',
            'mail.mailers.smtp.port' => 465,
        ]);
    }

    public function test_the_test_command_refuses_a_transport_that_delivers_to_nobody(): void
    {
        config(['mail.default' => 'log']);

        $exit = Artisan::call('padraord:mail-test', ['recipient' => 'alguem@padraord.com.br']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('não entrega a ninguém', Artisan::output());
    }

    public function test_the_test_command_refuses_an_invalid_address(): void
    {
        $this->smtpConfigured();

        $this->assertSame(2, Artisan::call('padraord:mail-test', ['recipient' => 'nao-e-email']));
    }

    public function test_the_test_command_sends_through_a_real_transport(): void
    {
        $this->smtpConfigured();
        Mail::fake();

        $exit = Artisan::call('padraord:mail-test', ['recipient' => 'operacao@padraord.com.br']);

        $this->assertSame(0, $exit);
        Mail::assertSent(DeliveryTestMessage::class, fn ($mail) => $mail->hasTo('operacao@padraord.com.br'));
        // Aceitação do provedor não é entrega na caixa; o comando precisa dizer isso.
        $this->assertStringContainsString('confirme o recebimento', Artisan::output());
    }

    public function test_the_recovery_message_carries_the_house_identity_and_is_in_portuguese(): void
    {
        $user = User::factory()->create(['name' => 'Rômulo']);
        $mail = (new ResetPasswordNotification('token-de-teste'))->toMail($user);

        $rendered = (string) $mail->render();

        $this->assertStringContainsString('Padrão RD', $rendered);
        $this->assertStringContainsString('Definir nova senha', $rendered);
        $this->assertStringContainsString('Olá, Rômulo', $rendered);
        // Violeta da marca, não o azul padrão do framework.
        $this->assertStringContainsString('#7657f5', $rendered);
        $this->assertStringNotContainsString('laravel.com/img', $rendered);
        $this->assertStringNotContainsString('Regards', $rendered);
    }

    public function test_the_message_states_the_link_expiry_taken_from_configuration(): void
    {
        config(['auth.passwords.users.expire' => 45]);
        $user = User::factory()->create();

        $rendered = (string) (new ResetPasswordNotification('token-de-teste'))->toMail($user)->render();

        $this->assertStringContainsString('45 minutos', $rendered);
    }
}
