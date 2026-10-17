<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'ASSET';
    case Liability = 'LIABILITY';
    case Equity = 'EQUITY';
    case Revenue = 'REVENUE';
    case Expense = 'EXPENSE';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Aset',
            self::Liability => 'Kewajiban',
            self::Equity => 'Modal',
            self::Revenue => 'Pendapatan',
            self::Expense => 'Beban',
        };
    }

    public function defaultNormalBalance(): NormalBalance
    {
        return match ($this) {
            self::Asset, self::Expense => NormalBalance::Debit,
            default => NormalBalance::Credit,
        };
    }

    /** Prefix kode akun sesuai klasifikasi (1-xxxx s/d 5-xxxx). */
    public function codePrefix(): string
    {
        return match ($this) {
            self::Asset => '1',
            self::Liability => '2',
            self::Equity => '3',
            self::Revenue => '4',
            self::Expense => '5',
        };
    }
}
