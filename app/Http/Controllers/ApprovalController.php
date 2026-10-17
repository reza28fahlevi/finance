<?php

namespace App\Http\Controllers;

use App\Enums\JournalStatus;
use App\Enums\TransactionStatus;
use App\Models\Approval;
use App\Models\Transaction;
use App\Services\ApprovalService;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function __construct(private ApprovalService $service) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Transaction::with(['creator', 'attachments', 'debitAccount', 'creditAccount'])
            ->where('status', TransactionStatus::PendingReview)
            ->when($request->query('filter') === 'large', fn ($q) => $q->where('amount', '>=', app(Settings::class)->get('approval_manager_threshold')));

        $queue = (clone $query)->orderBy('submitted_at')->get();

        $selected = $request->filled('id')
            ? Transaction::with(['creator', 'attachments', 'debitAccount', 'creditAccount', 'approvals.approver', 'period'])->find($request->integer('id'))
            : $queue->first(fn (Transaction $t) => $t->created_by !== $user->id) ?? $queue->first();

        $total = $queue->reduce(fn ($s, Transaction $t) => Money::add($s, (string) $t->amount), '0.00');
        $byType = $queue->groupBy(fn (Transaction $t) => $t->type->value)
            ->map(fn ($items) => $items->reduce(fn ($s, $t) => Money::add($s, (string) $t->amount), '0.00'));

        return view('approvals.index', [
            'queue' => $queue,
            'selected' => $selected,
            'total' => $total,
            'byType' => $byType,
            'threshold' => app(Settings::class)->get('approval_manager_threshold'),
            'tiered' => app(Settings::class)->get('approval_tiered'),
            'service' => $this->service,
        ]);
    }

    public function history(Request $request): View
    {
        $approvals = Approval::with(['transaction', 'approver'])
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->query('action')))
            ->when($request->boolean('mine'), fn ($q) => $q->where('approver_id', $request->user()->id))
            ->latest('acted_at')
            ->paginate(20)
            ->withQueryString();

        return view('approvals.history', [
            'approvals' => $approvals,
            'counts' => Approval::selectRaw('action, COUNT(*) as c')->groupBy('action')->pluck('c', 'action'),
        ]);
    }

    public function approve(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('decide', $transaction);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;

        ['journal' => $journal] = $this->service->approve($transaction, $request->user(), $note);

        $msg = "Transaksi {$transaction->number} disetujui. Jurnal {$journal->ref_no} diterbitkan";
        $msg .= $journal->status === JournalStatus::Posted ? ' dan langsung diposting ke Buku Besar.' : ' (Draft, menunggu posting Akuntan).';

        return redirect()->route('approvals.index')->with('success', $msg);
    }

    public function reject(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('decide', $transaction);
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:1000']], [
            'note.required' => 'Alasan penolakan wajib diisi.',
            'note.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $this->service->reject($transaction, $request->user(), $data['note']);

        return redirect()->route('approvals.index')->with('success', "Transaksi {$transaction->number} ditolak dan dikembalikan ke pembuat.");
    }
}
