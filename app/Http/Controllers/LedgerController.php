<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LedgerController extends Controller
{
    public function __construct(private LedgerService $ledger) {}

    public function ledger(Request $request): View
    {
        $period = $this->period($request);
        $accounts = Account::where('is_postable', true)->orderBy('code')->get();
        $account = $request->filled('account')
            ? $accounts->firstWhere('id', $request->integer('account'))
            : ($accounts->firstWhere('is_cash', true) ?? $accounts->first());

        return view('gl.ledger', [
            'accounts' => $accounts,
            'periods' => AccountingPeriod::orderByDesc('start_date')->get(),
            'period' => $period,
            'data' => $account && $period ? $this->ledger->accountLedger($account, $period) : null,
        ]);
    }

    public function summary(Request $request): View
    {
        $period = $this->period($request);

        return view('gl.summary', [
            'periods' => AccountingPeriod::orderByDesc('start_date')->get(),
            'period' => $period,
            'rows' => $period ? $this->ledger->summary($period, ! $request->boolean('hide_empty')) : collect(),
        ]);
    }

    public function trialBalance(Request $request): View
    {
        $period = $this->period($request);

        return view('gl.trial-balance', [
            'periods' => AccountingPeriod::orderByDesc('start_date')->get(),
            'period' => $period,
            'tb' => $period ? $this->ledger->trialBalance($period) : null,
        ]);
    }

    private function period(Request $request): ?AccountingPeriod
    {
        return $request->filled('period')
            ? AccountingPeriod::find($request->integer('period'))
            : AccountingPeriod::current();
    }
}
