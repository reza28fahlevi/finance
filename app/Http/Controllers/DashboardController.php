<?php

namespace App\Http\Controllers;

use App\Enums\JournalStatus;
use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\AccountingPeriod;
use App\Models\GlEntry;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Services\PostingService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PostingService $posting): View
    {
        $user = $request->user();
        $period = AccountingPeriod::current();

        // Ringkasan saldo kas/bank (akun bertanda is_cash).
        $cashAccounts = Account::where('is_cash', true)->where('is_postable', true)->orderBy('code')->get();
        $cashRows = $cashAccounts->map(function (Account $account) use ($period, $posting) {
            $balance = $period
                ? AccountBalance::where('account_id', $account->id)->where('period_id', $period->id)->first()
                : null;
            $closing = $balance ? Money::normalize($balance->closing_balance) : ($period ? $posting->previousClosing($account, $period) : '0.00');

            return ['account' => $account, 'balance' => $closing];
        });
        $cashTotal = $cashRows->reduce(fn ($s, $r) => Money::add($s, $r['balance']), '0.00');

        $cashIn = $cashOut = '0.00';
        if ($period && $cashAccounts->isNotEmpty()) {
            $sums = GlEntry::where('period_id', $period->id)
                ->whereIn('account_id', $cashAccounts->pluck('id'))
                ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
                ->first();
            $cashIn = Money::normalize($sums->d);
            $cashOut = Money::normalize($sums->c);
        }

        // Transaksi menunggu approval (untuk checker: kecuali buatannya sendiri).
        $pendingQuery = Transaction::with(['creator', 'attachments'])
            ->where('status', TransactionStatus::PendingReview)
            ->when($user->isChecker(), fn ($q) => $q->where('created_by', '!=', $user->id))
            ->when($user->hasPermission('transaction.manage'), fn ($q) => $q->where('created_by', $user->id));
        $pendingCount = (clone $pendingQuery)->count();
        $pendingTotal = Money::normalize((clone $pendingQuery)->sum('amount'));
        $pendingList = (clone $pendingQuery)->orderBy('submitted_at')->limit(5)->get();

        $draftJournals = $period ? JournalEntry::where('status', JournalStatus::Draft)->where('period_id', $period->id)->count() : 0;
        $draftJournalsAll = JournalEntry::where('status', JournalStatus::Draft)->count();

        $mutation = $period
            ? AccountBalance::where('period_id', $period->id)->selectRaw('COALESCE(SUM(total_debit),0) as d, COALESCE(SUM(total_credit),0) as c')->first()
            : null;
        $mutationDebit = Money::normalize($mutation->d ?? 0);
        $mutationCredit = Money::normalize($mutation->c ?? 0);

        $myDrafts = $user->hasPermission('transaction.manage')
            ? Transaction::where('created_by', $user->id)->whereIn('status', [TransactionStatus::Draft, TransactionStatus::Rejected])->latest()->limit(5)->get()
            : collect();

        return view('dashboard', compact(
            'user', 'period', 'cashRows', 'cashTotal', 'cashIn', 'cashOut',
            'pendingCount', 'pendingTotal', 'pendingList',
            'draftJournals', 'draftJournalsAll', 'mutationDebit', 'mutationCredit', 'myDrafts',
        ));
    }
}
