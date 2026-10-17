<?php

namespace App\Services;

use App\Enums\NormalBalance;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\AccountingPeriod;
use App\Models\GlEntry;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Laporan buku besar: per akun, ringkasan saldo semua akun, dan trial balance.
 */
class LedgerService
{
    public function __construct(private PostingService $posting) {}

    /**
     * @return array{account: Account, period: AccountingPeriod, opening: string, total_debit: string, total_credit: string, closing: string, entries: Collection<int, GlEntry>}
     */
    public function accountLedger(Account $account, AccountingPeriod $period): array
    {
        $balance = AccountBalance::where('account_id', $account->id)->where('period_id', $period->id)->first();
        $opening = $balance ? Money::normalize($balance->opening_balance) : $this->posting->previousClosing($account, $period);

        $entries = GlEntry::with(['journal:id,ref_no,source,transaction_id', 'journal.transaction:id,number,type'])
            ->where('account_id', $account->id)
            ->where('period_id', $period->id)
            ->orderBy('id')
            ->get();

        return [
            'account' => $account,
            'period' => $period,
            'opening' => $opening,
            'total_debit' => $balance ? Money::normalize($balance->total_debit) : '0.00',
            'total_credit' => $balance ? Money::normalize($balance->total_credit) : '0.00',
            'closing' => $balance ? Money::normalize($balance->closing_balance) : $opening,
            'entries' => $entries,
        ];
    }

    /**
     * Ringkasan saldo semua akun postable pada periode.
     *
     * @return Collection<int, array{account: Account, opening: string, total_debit: string, total_credit: string, closing: string}>
     */
    public function summary(AccountingPeriod $period, bool $includeEmpty = true): Collection
    {
        $balances = AccountBalance::where('period_id', $period->id)->get()->keyBy('account_id');

        return Account::where('is_postable', true)
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($balances, $period) {
                $b = $balances->get($account->id);
                $opening = $b ? Money::normalize($b->opening_balance) : $this->posting->previousClosing($account, $period);

                return [
                    'account' => $account,
                    'opening' => $opening,
                    'total_debit' => $b ? Money::normalize($b->total_debit) : '0.00',
                    'total_credit' => $b ? Money::normalize($b->total_credit) : '0.00',
                    'closing' => $b ? Money::normalize($b->closing_balance) : $opening,
                ];
            })
            ->when(! $includeEmpty, fn (Collection $rows) => $rows->filter(
                fn ($r) => ! (Money::isZero($r['opening']) && Money::isZero($r['total_debit']) && Money::isZero($r['total_credit']))
            )->values());
    }

    /**
     * Neraca saldo: saldo akhir tiap akun ditempatkan di kolom debit/kredit sesuai saldo normal & tanda.
     *
     * @return array{rows: Collection, total_debit: string, total_credit: string, balanced: bool, mutation_debit: string, mutation_credit: string}
     */
    public function trialBalance(AccountingPeriod $period): array
    {
        $rows = $this->summary($period, false)->map(function (array $row) {
            $closing = $row['closing'];
            $isDebitNormal = $row['account']->normal_balance === NormalBalance::Debit;
            $positive = Money::cmp($closing, '0') >= 0;

            // Saldo positif berada di sisi normal; saldo negatif pindah ke sisi lawan.
            $debitSide = $isDebitNormal === $positive;

            return $row + [
                'debit' => $debitSide ? Money::abs($closing) : '0.00',
                'credit' => $debitSide ? '0.00' : Money::abs($closing),
            ];
        });

        $totalDebit = $rows->reduce(fn ($s, $r) => Money::add($s, $r['debit']), '0.00');
        $totalCredit = $rows->reduce(fn ($s, $r) => Money::add($s, $r['credit']), '0.00');

        return [
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced' => Money::cmp($totalDebit, $totalCredit) === 0,
            'mutation_debit' => $rows->reduce(fn ($s, $r) => Money::add($s, $r['total_debit']), '0.00'),
            'mutation_credit' => $rows->reduce(fn ($s, $r) => Money::add($s, $r['total_credit']), '0.00'),
        ];
    }
}
