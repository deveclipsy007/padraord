<?php

namespace App\Enums;

enum EventType: string
{
    case CONGRESSO = 'congresso';
    case CONVENCAO = 'convencao';
    case CONFERENCIA = 'conferencia';
    case SEMINARIO = 'seminario';
    case WORKSHOP = 'workshop';
    case TREINAMENTO = 'treinamento';
    case FEIRA = 'feira';
    case EXPOSICAO = 'exposicao';
    case LANCAMENTO = 'lancamento';
    case ATIVACAO = 'ativacao';
    case PREMIO = 'premiacao';
    case CONFRATERNIZACAO = 'confraternizacao';
    case CASAMENTO = 'casamento';
    case FORMATURA = 'formatura';
    case ANIVERSARIO = 'aniversario';
    case SHOW = 'show';
    case FESTIVAL = 'festival';
    case ESPORTIVO = 'esportivo';
    case INSTITUCIONAL = 'institucional';
    case RELIGIOSO = 'religioso';
}
