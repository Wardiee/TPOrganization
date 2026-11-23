<?php

namespace App\Enums;

enum HistoryKind: string
{
    case Org = 'org';
    case Members = 'members';
    case Activity = 'activity';
}
