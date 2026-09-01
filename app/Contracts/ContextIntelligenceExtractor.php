<?php

namespace App\Contracts;

use App\Data\ContextIntelligenceResult;
use App\Models\CaseContextEntry;

interface ContextIntelligenceExtractor
{
    public function extract(CaseContextEntry $entry, array $caseContext): ContextIntelligenceResult;
}
