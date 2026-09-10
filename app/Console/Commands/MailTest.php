<?php

namespace App\Console\Commands;

use App\Mail\DeliveryTestMessage;
use App\Services\SystemHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Confirma que o transporte configurado entrega a uma pessoa de verdade.
 * Sem isso a redefinição de senha e o envio de proposta falham em silêncio.
 */
class MailTest extends Command
{
    protected $signature = 'padraord:mail-test {recipient : Endereço que deve receber a mensagem}';

    protected $description = 'Envia uma mensagem real para confirmar que o e-mail sai do servidor';

    public function handle(): int
    {
        $recipient = (string) $this->argument('recipient');
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error('Informe um endereço de e-mail válido.');

            return self::INVALID;
        }

        $status = SystemHealth::mailStatus();
        if ($status === 'not_delivering') {
            $this->components->error('MAIL_MAILER está como "'.config('mail.default').'", que grava num arquivo e não entrega a ninguém.');
            $this->components->bulletList(['Configure MAIL_MAILER=smtp com os dados da Hostinger antes de testar.']);

            return self::FAILURE;
        }
        if ($status === 'unconfigured') {
            $this->components->error('Falta MAIL_FROM_ADDRESS ou o transporte não está definido.');

            return self::FAILURE;
        }

        $this->components->info('Enviando por '.config('mail.default').' via '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));

        try {
            Mail::to($recipient)->send(new DeliveryTestMessage(now()->format('d/m/Y H:i:s')));
        } catch (Throwable $e) {
            $this->components->error('O provedor recusou o envio.');
            $this->components->bulletList([
                $e->getMessage(),
                'Confira usuário, senha, porta e MAIL_SCHEME. Porta 465 usa smtps; porta 587 usa smtp.',
            ]);

            return self::FAILURE;
        }

        $this->components->info('Mensagem aceita pelo provedor e endereçada a '.$recipient.'.');
        $this->components->bulletList(['O provedor aceitar não garante a entrega na caixa: confirme o recebimento, inclusive no spam.']);

        return self::SUCCESS;
    }
}
