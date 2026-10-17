<?php

namespace App\Http\Controllers;

use App\Enums\JournalStatus;
use App\Enums\PeriodStatus;
use App\Models\AccountingPeriod;
use App\Services\PeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PeriodController extends Controller
{
    public function __construct(private PeriodService $periods) {}

    public function index(Request $request): View
    {
        $status = PeriodStatus::tryFrom((string) $request->query('status'));
        $year = $request->integer('year') ?: null;

        $periods = AccountingPeriod::with('closedBy')
            ->withCount([
                'journals as posted_journals_count' => fn ($q) => $q->whereIn('status', [JournalStatus::Posted, JournalStatus::Reversed]),
                'journals as draft_journals_count' => fn ($q) => $q->where('status', JournalStatus::Draft),
                'transactions',
            ])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($year, fn ($q) => $q->whereYear('start_date', $year))
            ->orderByDesc('start_date')
            ->paginate(12)
            ->withQueryString();

        $current = AccountingPeriod::current();
        $next = AccountingPeriod::orderByDesc('start_date')->first()?->start_date->copy()->addMonthNoOverflow()->format('Y-m') ?? now()->format('Y-m');

        return view('master.periods.index', [
            'periods' => $periods,
            'current' => $current,
            'status' => $status,
            'year' => $year,
            'years' => AccountingPeriod::selectRaw('YEAR(start_date) as y')->distinct()->orderByDesc('y')->pluck('y'),
            'closedCount' => AccountingPeriod::where('status', PeriodStatus::Closed)->count(),
            'openCount' => AccountingPeriod::where('status', PeriodStatus::Open)->count(),
            'suggestedCode' => $next,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'date_format:Y-m', 'unique:accounting_periods,code'],
        ], [
            'code.date_format' => 'Format periode harus YYYY-MM.',
            'code.unique' => 'Periode tersebut sudah ada.',
        ]);

        $period = $this->periods->create($data['code']);

        return back()->with('success', "Periode {$period->code} dibuat dengan status OPEN.");
    }

    public function close(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $this->periods->close($period, $request->user());

        return back()->with('success', "Periode {$period->code} berhasil ditutup (CLOSED). Transaksi & jurnal di periode ini kini read-only.");
    }

    public function reopen(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']], [
            'reason.required' => 'Alasan pembukaan kembali periode wajib diisi.',
        ]);

        $this->periods->reopen($period, $data['reason']);

        return back()->with('success', "Periode {$period->code} dibuka kembali (OPEN).");
    }
}
