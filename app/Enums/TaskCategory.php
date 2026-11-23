<?php

namespace App\Enums;

enum TaskCategory: string
{
    case Members = 'Members';
    case Structure = 'Structure';
    case Activities = 'Activities';
    case Birthdays = 'Birthdays';
    case Records = 'Records';
}
