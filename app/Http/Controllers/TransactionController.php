<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Http\Requests\TransactionRequest;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalTemplate;
use App\Models\Transaction;
use App\Services\ApprovalService;
use App\Services\TransactionService;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function __construct(private TransactionService $service) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Transaction::class);

        $base = $this->filtered($request, withStatus: false);
        $statusCounts = (clone $base)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        $transactions = $this->filtered($request)
            ->with(['creator', 'debitAccount', 'creditAccount'])
            ->withCount('attachments')
            ->orderBy(...$this->sort($request))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15) ?: 15)
            ->withQueryString();

        $period = $request->filled('period') ? AccountingPeriod::find($request->integer('period')) : null;
        $kpiQuery = Transaction::query()->when($period, fn ($q) => $q->where('period_id', $period->id));
        $cashIn = (clone $kpiQuery)->where('type', TransactionType::CashIn)->whereIn('status', [TransactionStatus::Approved, TransactionStatus::Journaled]);
        $cashOut = (clone $kpiQuery)->where('type', TransactionType::CashOut)->whereIn('status', [TransactionStatus::Approved, TransactionStatus::Journaled]);
        $pending = (clone $kpiQuery)->where('status', TransactionStatus::PendingReview);

        return view('transactions.index', [
            'transactions' => $transactions,
            'statusCounts' => $statusCounts,
            'periods' => AccountingPeriod::orderByDesc('start_date')->get(),
            'kpi' => [
                'cash_in' => Money::normalize((clone $cashIn)->sum('amount')),
                'cash_in_count' => (clone $cashIn)->count(),
                'cash_out' => Money::normalize((clone $cashOut)->sum('amount')),
                'cash_out_count' => (clone $cashOut)->count(),
                'pending' => (clone $pending)->count(),
                'pending_amount' => Money::normalize((clone $pending)->sum('amount')),
                'journaled' => (clone $kpiQuery)->where('status', TransactionStatus::Journaled)->count(),
            ],
        ]);
    }

    /** Ekspor CSV daftar transaksi sesuai filter aktif. */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Transaction::class);
        $query = $this->filtered($request)->with(['creator', 'debitAccount', 'creditAccount'])->orderBy('trx_date');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar terbaca benar di Excel
            fputcsv($out, ['Nomor', 'Tanggal', 'Jenis', 'Deskripsi', 'Pihak', 'Akun Debit', 'Akun Kredit', 'Nominal', 'Status', 'Dibuat Oleh']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $t) {
                    fputcsv($out, [
                        $t->number, $t->trx_date->format('Y-m-d'), $t->type->label(), $t->description, $t->party_name,
                        $t->debitAccount->code, $t->creditAccount->code, $t->amount, $t->status->label(), $t->creator->name,
                    ]);
                }
            });
            fclose($out);
        }, 'transaksi-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(string $type): View
    {
        Gate::authorize('create', Transaction::class);
        $type = $this->resolveType($type);
        $template = JournalTemplate::forType($type);

        $transaction = new Transaction([
            'type' => $type,
            'trx_date' => now()->toDateString(),
            'debit_account_id' => $template?->debit_account_id,
            'credit_account_id' => $template?->credit_account_id,
        ]);

        return view('transactions.form', $this->formData($transaction));
    }

    public function store(TransactionRequest $request, string $type): RedirectResponse
    {
        Gate::authorize('create', Transaction::class);
        $type = $this->resolveType($type);

        $transaction = $this->service->create($type, $request->validated(), $request->user(), $request->file('attachments', []));

        if ($request->input('action') === 'submit') {
            return $this->submitAfterSave($transaction, $request, 'dibuat');
        }

        return redirect()->route('transactions.show', $transaction)->with('success', "Transaksi {$transaction->number} tersimpan sebagai Draft.");
    }

    public function show(Transaction $transaction, ApprovalService $approvals): View
    {
        Gate::authorize('view', $transaction);
        $transaction->load(['creator', 'debitAccount', 'creditAccount', 'attachments.uploader', 'approvals.approver', 'journal.lines.account', 'period']);

        return view('transactions.show', [
            'transaction' => $transaction,
            'requiresManager' => $approvals->requiresManager($transaction),
            'attachmentThreshold' => app(Settings::class)->get('attachment_required_above'),
        ]);
    }

    public function edit(Transaction $transaction): View
    {
        Gate::authorize('update', $transaction);
        $transaction->load('attachments');

        return view('transactions.form', $this->formData($transaction));
    }

    public function update(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('update', $transaction);

        $this->service->update($transaction, $request->validated(), $request->user(), $request->file('attachments', []));

        if ($request->input('action') === 'submit') {
            return $this->submitAfterSave($transaction, $request, 'diperbarui');
        }

        return redirect()->route('transactions.show', $transaction)->with('success', "Transaksi {$transaction->number} diperbarui.");
    }

    public function submit(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('submit', $transaction);
        $this->service->submit($transaction, $request->user());

        return back()->with('success', "Transaksi {$transaction->number} diajukan untuk review (Pending Review).");
    }

    public function cancel(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('cancel', $transaction);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;
        $this->service->cancel($transaction, $reason);

        return back()->with('success', "Transaksi {$transaction->number} dibatalkan.");
    }

    /** Simpan sudah berhasil; bila submit gagal, transaksi tetap Draft dan pengguna diarahkan ke detail. */
    private function submitAfterSave(Transaction $transaction, Request $request, string $verb): RedirectResponse
    {
        try {
            $this->service->submit($transaction, $request->user());
        } catch (BusinessRuleException $e) {
            return redirect()->route('transactions.show', $transaction)
                ->with('error', "Transaksi {$transaction->number} {$verb} sebagai Draft, tetapi belum dapat diajukan: {$e->getMessage()}");
        }

        return redirect()->route('transactions.show', $transaction)->with('success', "Transaksi {$transaction->number} {$verb} dan diajukan untuk review.");
    }

    /** @return array<string, mixed> */
    private function formData(Transaction $transaction): array
    {
        $settings = app(Settings::class);

        return [
            'transaction' => $transaction,
            'type' => $transaction->type,
            'accounts' => Account::postable()->orderBy('code')->get(),
            'openPeriods' => AccountingPeriod::open()->orderBy('start_date')->get(),
            'attachmentThreshold' => $settings->get('attachment_required_above'),
            'attachmentMaxKb' => $settings->get('attachment_max_kb'),
        ];
    }

    private function resolveType(string $slug): TransactionType
    {
        try {
            return TransactionType::fromSlug($slug);
        } catch (\ValueError) {
            abort(404);
        }
    }

    private function filtered(Request $request, bool $withStatus = true): Builder
    {
        return Transaction::query()
            ->when($withStatus && $request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('period'), fn ($q) => $q->where('period_id', $request->integer('period')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('trx_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('trx_date', '<=', $request->date('to')))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', $request->user()->id))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->query('q')).'%';
                $q->where(fn ($w) => $w->where('number', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('party_name', 'like', $term)
                    ->orWhere('reference', 'like', $term));
            });
    }

    /** @return array{0:string, 1:string} */
    private function sort(Request $request): array
    {
        return match ($request->query('sort')) {
            'oldest' => ['trx_date', 'asc'],
            'amount_desc' => ['amount', 'desc'],
            'amount_asc' => ['amount', 'asc'],
            default => ['trx_date', 'desc'],
        };
    }
}
