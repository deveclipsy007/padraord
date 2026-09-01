<?php

namespace App\Enums;

enum CommercialStage: string
{
    case LEAD = 'lead';
    case QUALIFICATION = 'qualification';
    case MEETING = 'meeting';
    case INITIAL_BRIEFING = 'initial_briefing';
    case VIABILITY_OFFER = 'viability_offer';
    case VIABILITY_CONTRACTED = 'viability_contracted';
    case LOST = 'lost';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::LEAD => 'Lead',
            self::QUALIFICATION => 'Qualificação',
            self::MEETING => 'Reunião',
            self::INITIAL_BRIEFING => 'Briefing inicial',
            self::VIABILITY_OFFER => 'Oferta de Viabilidade',
            self::VIABILITY_CONTRACTED => 'Viabilidade contratada',
            self::LOST => 'Perdido',
            self::CANCELLED => 'Cancelado',
        };
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::LEAD, self::QUALIFICATION, self::MEETING, self::INITIAL_BRIEFING, self::VIABILITY_OFFER, self::VIABILITY_CONTRACTED];
    }
}
