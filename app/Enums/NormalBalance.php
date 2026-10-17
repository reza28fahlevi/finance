<?php

namespace App\Enums;

enum NormalBalance: string
{
    case Debit = 'DEBIT';
    case Credit = 'CREDIT';

    public function label(): string
    {
        return $this === self::Debit ? 'Debit' : 'Kredit';
    }
}
