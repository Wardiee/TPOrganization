<?php

namespace App\Enums;

enum MemberStatus: string
{
    case Active = 'active';
    case Floating = 'floating';
    case Inactive = 'inactive';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
