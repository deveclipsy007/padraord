<?php

namespace Tests\Unit;

use App\AI\AiCompletion;
use App\AI\NullAiProvider;
use PHPUnit\Framework\TestCase;

class AiProviderTest extends TestCase
{
    public function test_manual_fallback_is_explicitly_unavailable_without_blocking_the_workflow(): void
    {
        $completion = (new NullAiProvider)->analyzeBriefing('Conversa do cliente');

        $this->assertSame(AiCompletion::UNAVAILABLE, $completion->status);
        $this->assertFalse($completion->succeeded());
        $this->assertSame('manual_fallback', $completion->provider);
        $this->assertNotEmpty($completion->error);
    }
}
