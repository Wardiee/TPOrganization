<?php

namespace App\Enums;

enum Branch: string
{
    case Trop = 'TROP';
    case Trops = 'TROPS';

    /**
     * Short name used in the designs, e.g. "TROP-9 · Main".
     */
    public function label(): string
    {
        return match ($this) {
            self::Trop => 'Main',
            self::Trops => 'Ext',
        };
    }
}
