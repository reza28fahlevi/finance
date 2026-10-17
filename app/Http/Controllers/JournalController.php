<?php

namespace App\Http\Controllers;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Services\JournalService;
use App\Services\PostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JournalController extends Controller
{
    public function __construct(
        private JournalService $journals,
        private PostingService $posting,
    ) {}

    public function index(Request $request): View
    {
        $query = JournalEntry::with(['period', 'creator', 'transaction:id,number'])
            ->withSum('lines as total_debit', 'debit')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->query('source')))
            ->when($request->filled('period'), fn ($q) => $q->where('period_id', $request->integer('period')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->query('q')).'%';
                $q->where(fn ($w) => $w->where('ref_no', 'like', $term)->orWhere('description', 'like', $term));
            });

        return view('journals.index', [
            'journals' => $query->orderByDesc('entry_date')->orderByDesc('id')->paginate(20)->withQueryString(),
            'periods' => AccountingPeriod::orderByDesc('start_date')->get(),
            'counts' => JournalEntry::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(JournalEntry $journal): View
    {
        $journal->load(['lines.account', 'period', 'creator', 'poster', 'transaction', 'reversalOf', 'reversedBy']);

        return view('journals.show', ['journal' => $journal]);
    }

    public function create(): View
    {
        return view('journals.form', $this->formData(new JournalEntry([
            'entry_date' => now()->toDateString(),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $journal = $this->journals->createManual($data, $request->user());

        if ($request->input('action') === 'post') {
            return $this->postAfterSave($journal, $request);
        }

        return redirect()->route('journals.show', $journal)->with('success', "Jurnal {$journal->ref_no} tersimpan sebagai Draft.");
    }

    public function edit(JournalEntry $journal): View
    {
        if ($journal->status !== JournalStatus::Draft || $journal->source === JournalSource::Auto) {
            throw new BusinessRuleException('Hanya jurnal manual berstatus DRAFT yang dapat diubah.');
        }
        $journal->load('lines');

        return view('journals.form', $this->formData($journal));
    }

    public function update(Request $request, JournalEntry $journal): RedirectResponse
    {
        $data = $this->validated($request);
        $this->journals->updateDraft($journal, $data);

        if ($request->input('action') === 'post') {
            return $this->postAfterSave($journal, $request);
        }

        return redirect()->route('journals.show', $journal)->with('success', "Jurnal {$journal->ref_no} diperbarui.");
    }

    public function destroy(JournalEntry $journal): RedirectResponse
    {
        $ref = $journal->ref_no;
        $this->journals->deleteDraft($journal);

        return redirect()->route('journals.index')->with('success', "Jurnal draft {$ref} dihapus.");
    }

    public function post(Request $request, JournalEntry $journal): RedirectResponse
    {
        $this->posting->post($journal, $request->user());

        return back()->with('success', "Jurnal {$journal->ref_no} berhasil diposting ke General Ledger.");
    }

    /** Daftar jurnal POSTED yang dapat dibalik + riwayat jurnal pembalik. */
    public function reversals(Request $request): View
    {
        $posted = JournalEntry::with('period')
            ->withSum('lines as total_debit', 'debit')
            ->where('status', JournalStatus::Posted)
            ->when($request->filled('q'), fn ($q) => $q->where('ref_no', 'like', '%'.$request->query('q').'%'))
            ->orderByDesc('entry_date')->orderByDesc('id')
            ->paginate(15, ['*'], 'posted_page')
            ->withQueryString();

        $history = JournalEntry::with(['reversalOf', 'creator'])
            ->withSum('lines as total_debit', 'debit')
            ->whereNotNull('reversal_of_id')
            ->latest()
            ->limit(20)
            ->get();

        return view('journals.reversals', compact('posted', 'history'));
    }

    public function reverse(Request $request, JournalEntry $journal): RedirectResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan pembalikan wajib diisi.',
        ]);

        $reversal = $this->journals->reverse($journal, $request->user(), $data['entry_date'], $data['reason'], $this->posting);

        return redirect()->route('journals.show', $reversal)->with('success', "Jurnal pembalik {$reversal->ref_no} diposting. Jurnal {$journal->ref_no} kini berstatus REVERSED.");
    }

    public function batch(Request $request): View
    {
        $drafts = JournalEntry::with(['period', 'transaction:id,number'])
            ->withSum('lines as total_debit', 'debit')
            ->withSum('lines as total_credit', 'credit')
            ->where('status', JournalStatus::Draft)
            ->when($request->filled('period'), fn ($q) => $q->where('period_id', $request->integer('period')))
            ->orderBy('entry_date')->orderBy('id')
            ->get();

        return view('journals.batch', [
            'drafts' => $drafts,
            'periods' => AccountingPeriod::orderByDesc('start_date')->get(),
        ]);
    }

    public function postBatch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'journal_ids' => ['required', 'array', 'min:1'],
            'journal_ids.*' => ['integer'],
        ], ['journal_ids.required' => 'Pilih minimal satu jurnal untuk diposting.']);

        $results = $this->posting->postBatch($data['journal_ids'], $request->user());
        $ok = collect($results)->where('success', true)->count();
        $fail = count($results) - $ok;

        return redirect()->route('journals.batch')
            ->with($fail ? 'error' : 'success', "Posting batch selesai: {$ok} berhasil, {$fail} gagal.")
            ->with('batch_results', $results);
    }

    private function postAfterSave(JournalEntry $journal, Request $request): RedirectResponse
    {
        try {
            $this->posting->post($journal, $request->user());
        } catch (BusinessRuleException $e) {
            return redirect()->route('journals.show', $journal)->with('error', "Jurnal {$journal->ref_no} tersimpan sebagai Draft, tetapi gagal diposting: {$e->getMessage()}");
        }

        return redirect()->route('journals.show', $journal)->with('success', "Jurnal {$journal->ref_no} tersimpan dan diposting ke General Ledger.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:2', 'max:100'],
            'lines.*.account_id' => ['nullable', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
        ], [
            'lines.min' => 'Jurnal minimal terdiri dari 2 baris.',
            'lines.*.debit.regex' => 'Nilai debit maksimal 2 angka desimal.',
            'lines.*.credit.regex' => 'Nilai kredit maksimal 2 angka desimal.',
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(JournalEntry $journal): array
    {
        $lines = $journal->exists
            ? $journal->lines->map(fn ($l) => [
                'account_id' => (string) $l->account_id,
                'description' => $l->description ?? '',
                'debit' => (float) $l->debit > 0 ? (string) $l->debit : '',
                'credit' => (float) $l->credit > 0 ? (string) $l->credit : '',
            ])->all()
            : [];

        return [
            'journal' => $journal,
            'initialLines' => old('lines', $lines),
            'accounts' => Account::postable()->orderBy('code')->get(),
            'openPeriods' => AccountingPeriod::open()->orderBy('start_date')->get(),
            'nextRef' => $journal->ref_no ?? 'Dibuat otomatis saat disimpan',
        ];
    }
}
