<?php

namespace App\Enums;

enum JournalStatus: string
{
    case Draft = 'DRAFT';
    case Posted = 'POSTED';
    case Reversed = 'REVERSED';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-surface-container-highest text-on-surface-variant',
            self::Posted => 'bg-success-container/60 text-success',
            self::Reversed => 'bg-error-container/50 text-on-error-container',
        };
    }
}
