<?php

namespace App\AI;

interface AiProvider
{
    /**
     * Returns a structured, reviewable briefing diff. Providers must never mutate domain data.
     */
    public function analyzeBriefing(string $transcript, array $context = []): AiCompletion;
}
