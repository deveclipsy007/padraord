<?php

namespace App\Enums;

enum EventFormat: string
{
    case PRESENCIAL = 'presencial';
    case HIBRIDO = 'hibrido';
    case ONLINE = 'online';
}
