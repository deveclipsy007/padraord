<?php

namespace App\AI;

final class NullAiProvider implements AiProvider
{
    public function analyzeBriefing(string $transcript, array $context = []): AiCompletion
    {
        return AiCompletion::unavailable(
            'Edição manual disponível. O provedor real exige modo OpenAI, chave configurada e política de uso de dados validada.',
        );
    }
}
