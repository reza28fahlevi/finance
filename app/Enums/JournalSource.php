<?php

namespace App\Enums;

enum JournalSource: string
{
    case Auto = 'AUTO';
    case Manual = 'MANUAL';

    public function label(): string
    {
        return $this === self::Auto ? 'Otomatis' : 'Manual';
    }
}
