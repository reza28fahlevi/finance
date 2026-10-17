<?php

namespace App\Services;

use App\Enums\JournalStatus;
use App\Enums\PeriodStatus;
use App\Enums\TransactionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AccountingPeriod;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PeriodService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Pastikan tanggal berada pada periode OPEN. Mengembalikan periode tersebut.
     *
     * @throws BusinessRuleException
     */
    public function assertOpenForDate(CarbonInterface|string $date): AccountingPeriod
    {
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);
        $period = AccountingPeriod::forDate($date);

        if (! $period) {
            throw new BusinessRuleException("Tidak ada periode akuntansi untuk tanggal {$date->format('d/m/Y')}.");
        }

        if (! $period->isOpen()) {
            throw new BusinessRuleException("Periode {$period->code} sudah CLOSED. Transaksi, jurnal, dan posting tidak diizinkan.");
        }

        return $period;
    }

    /** @throws BusinessRuleException */
    public function assertOpen(AccountingPeriod $period): void
    {
        if (! $period->fresh()->isOpen()) {
            throw new BusinessRuleException("Periode {$period->code} sudah CLOSED.");
        }
    }

    /** Buat periode bulanan dari kode YYYY-MM. */
    public function create(string $code): AccountingPeriod
    {
        $start = Carbon::createFromFormat('Y-m-d', $code.'-01')->startOfDay();

        if (AccountingPeriod::where('code', $code)->exists()) {
            throw new BusinessRuleException("Periode {$code} sudah ada.");
        }

        return AccountingPeriod::create([
            'code' => $code,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
            'status' => PeriodStatus::Open,
        ]);
    }

    /**
     * Tutup periode. Ditolak bila masih ada jurnal DRAFT atau transaksi yang belum selesai.
     *
     * @throws BusinessRuleException
     */
    public function close(AccountingPeriod $period, User $user): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $user) {
            $period = AccountingPeriod::lockForUpdate()->findOrFail($period->id);

            if (! $period->isOpen()) {
                throw new BusinessRuleException("Periode {$period->code} sudah CLOSED.");
            }

            $draftJournals = $period->journals()->where('status', JournalStatus::Draft)->count();
            if ($draftJournals > 0) {
                throw new BusinessRuleException("Masih ada {$draftJournals} jurnal DRAFT di periode {$period->code}. Posting atau hapus terlebih dahulu.");
            }

            $openTrx = $period->transactions()
                ->whereIn('status', [TransactionStatus::PendingReview, TransactionStatus::Approved])
                ->count();
            if ($openTrx > 0) {
                throw new BusinessRuleException("Masih ada {$openTrx} transaksi Pending Review/Approved yang belum dijurnal di periode {$period->code}.");
            }

            $period->update([
                'status' => PeriodStatus::Closed,
                'closed_at' => now(),
                'closed_by' => $user->id,
            ]);
            $this->audit->statusChange($period, 'period.closed', PeriodStatus::Open->value, PeriodStatus::Closed->value);

            return $period;
        });
    }

    public function reopen(AccountingPeriod $period, string $reason): AccountingPeriod
    {
        if ($period->isOpen()) {
            throw new BusinessRuleException("Periode {$period->code} masih OPEN.");
        }

        $period->update(['status' => PeriodStatus::Open, 'closed_at' => null, 'closed_by' => null]);
        $this->audit->statusChange($period, 'period.reopened', PeriodStatus::Closed->value, PeriodStatus::Open->value, $reason);

        return $period;
    }
}
