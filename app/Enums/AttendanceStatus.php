<?php

namespace App\Enums;

/**
 * Stored on attendance rows. "Absent" is never stored: no row means absent.
 *
 * "Not required" (Exempt) is for members who weren't expected at that activity; they count as neither
 * present nor absent, and are left out of turnout.
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Excused = 'excused';
    case Absent = 'absent';
    case Exempt = 'exempt';

    public function label(): string
    {
        return match ($this) {
            self::Exempt => 'Not required',
            default => ucfirst($this->value),
        };
    }

    /**
     * Label for the phone's compact buttons.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Present => 'P',
            self::Excused => 'E',
            self::Absent => 'A',
            self::Exempt => 'N/A',
        };
    }
}
