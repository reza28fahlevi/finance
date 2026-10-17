<?php

namespace App\Enums;

enum TransactionType: string
{
    case CashIn = 'CASH_IN';
    case CashOut = 'CASH_OUT';
    case PurchaseInvoice = 'PURCHASE_INV';
    case SalesInvoice = 'SALES_INV';

    public function label(): string
    {
        return match ($this) {
            self::CashIn => 'Penerimaan Kas',
            self::CashOut => 'Pengeluaran Kas',
            self::PurchaseInvoice => 'Invoice Pembelian',
            self::SalesInvoice => 'Invoice Penjualan',
        };
    }

    /** Kode jenis untuk token {TYPE} pada nomor transaksi. */
    public function code(): string
    {
        return match ($this) {
            self::CashIn => 'BKM',
            self::CashOut => 'BKK',
            self::PurchaseInvoice => 'INV-IN',
            self::SalesInvoice => 'INV-OUT',
        };
    }

    public function slug(): string
    {
        return match ($this) {
            self::CashIn => 'penerimaan-kas',
            self::CashOut => 'pengeluaran-kas',
            self::PurchaseInvoice => 'invoice-pembelian',
            self::SalesInvoice => 'invoice-penjualan',
        };
    }

    public static function fromSlug(string $slug): self
    {
        foreach (self::cases() as $case) {
            if ($case->slug() === $slug) {
                return $case;
            }
        }

        throw new \ValueError("Jenis transaksi tidak dikenal: {$slug}");
    }

    public function icon(): string
    {
        return match ($this) {
            self::CashIn => 'arrow_downward',
            self::CashOut => 'arrow_outward',
            self::PurchaseInvoice => 'shopping_cart',
            self::SalesInvoice => 'storefront',
        };
    }

    public function isInvoice(): bool
    {
        return in_array($this, [self::PurchaseInvoice, self::SalesInvoice], true);
    }

    /** Label pihak terkait pada form. */
    public function partyLabel(): string
    {
        return match ($this) {
            self::SalesInvoice => 'Pelanggan',
            self::PurchaseInvoice => 'Vendor',
            default => 'Pihak Terkait',
        };
    }

    /** Label akun sisi debit & kredit pada form transaksi. */
    public function debitLabel(): string
    {
        return match ($this) {
            self::CashIn => 'Masuk ke Akun Kas/Bank (Debit)',
            self::CashOut => 'Akun Beban / Utang (Debit)',
            self::SalesInvoice => 'Akun Piutang (Debit)',
            self::PurchaseInvoice => 'Akun Beban / Persediaan (Debit)',
        };
    }

    public function creditLabel(): string
    {
        return match ($this) {
            self::CashIn => 'Akun Sumber: Pendapatan / Piutang (Kredit)',
            self::CashOut => 'Keluar dari Akun Kas/Bank (Kredit)',
            self::SalesInvoice => 'Akun Pendapatan (Kredit)',
            self::PurchaseInvoice => 'Akun Utang Usaha (Kredit)',
        };
    }
}
