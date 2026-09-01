<?php

namespace App\Enums;

enum OpportunityStage: string
{
    case LEAD = 'lead';
    case QUALIFICATION = 'qualification';
    case BRIEFING = 'briefing';
    case BUDGET = 'budget';
    case PROPOSAL = 'proposal';
    case NEGOTIATION = 'negotiation';
    case CONTRACT = 'contract';
    case PRE_PRODUCTION = 'pre_production';
    case PRODUCTION = 'production';
    case POST_EVENT = 'post_event';
    case CLOSED = 'closed';
    case LOST = 'lost';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::LEAD => 'Lead',
            self::QUALIFICATION => 'Qualificação',
            self::BRIEFING => 'Briefing',
            self::BUDGET => 'Orçamento',
            self::PROPOSAL => 'Proposta',
            self::NEGOTIATION => 'Negociação',
            self::CONTRACT => 'Contrato',
            self::PRE_PRODUCTION => 'Pré-produção',
            self::PRODUCTION => 'Execução',
            self::POST_EVENT => 'Pós-evento',
            self::CLOSED => 'Encerrado',
            self::LOST => 'Perdido',
            self::CANCELLED => 'Cancelado',
        };
    }
}
