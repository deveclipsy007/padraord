<?php

namespace Tests\Unit;

use App\Enums\OpportunityStage;
use PHPUnit\Framework\TestCase;

class OpportunityStageTest extends TestCase
{
    public function test_it_exposes_the_complete_padrao_rd_lifecycle_in_order(): void
    {
        $this->assertSame([
            'lead',
            'qualification',
            'briefing',
            'budget',
            'proposal',
            'negotiation',
            'contract',
            'pre_production',
            'production',
            'post_event',
            'closed',
            'lost',
            'cancelled',
        ], array_map(fn (OpportunityStage $stage) => $stage->value, OpportunityStage::cases()));
    }
}
