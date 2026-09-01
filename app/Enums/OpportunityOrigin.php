<?php

namespace App\Enums;

enum OpportunityOrigin: string
{
    case REFERRAL = 'referral';
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
    case RETURNING_CLIENT = 'returning_client';
    case PARTNER = 'partner';
    case ORGANIC = 'organic';
    case OTHER = 'other';
}
