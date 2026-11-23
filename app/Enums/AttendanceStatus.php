<?php

namespace App\Enums;

/**
 * Stored on attendance rows. "Absent" is never stored: no row means absent.
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Excused = 'excused';
    case Absent = 'absent';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
