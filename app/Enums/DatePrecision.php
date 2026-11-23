<?php

namespace App\Enums;

/**
 * How exact a history date is: a full day, or only the year (e.g. "founded 2020").
 */
enum DatePrecision: string
{
    case Day = 'day';
    case Year = 'year';
}
