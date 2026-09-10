<x-mail::message>
# O envio de e-mail está funcionando

Esta é uma mensagem de teste do Padrão RD OS.

Se você recebeu, o transporte configurado entrega de verdade — a redefinição de senha e o envio de proposta chegam ao destinatário.

<x-mail::panel>
Enviada em {{ $sentAt }} pelo transporte **{{ config('mail.default') }}**.
</x-mail::panel>

Nenhuma ação é necessária.

Equipe Padrão RD
</x-mail::message>
