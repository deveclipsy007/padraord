<?php

namespace App\Channels;

use App\Contracts\CommercialChannel;

final class ManualCommercialChannel implements CommercialChannel
{
    public function prepare(string $event, array $payload): array
    {
        return [
            'channel' => 'manual',
            'event' => $event,
            'payload' => $payload,
        ];
    }
}
