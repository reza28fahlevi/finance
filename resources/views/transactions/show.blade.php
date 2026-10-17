@php use App\Support\Money; use App\Enums\TransactionStatus; @endphp
<x-app-layout :title="$transaction->number">
    <x-page-header :eyebrow="$transaction->type->label()" :context="'Periode '.$transaction->period->code" :title="$transaction->number"
                   :subtitle="$transaction->description">
        <x-slot:actions>
            <a href="{{ route('transactions.index') }}" class="btn-secondary"><span class="material-symbols-outlined">arrow_back</span> Daftar</a>
            @can('update', $transaction)
                <a href="{{ route('transactions.edit', $transaction) }}" class="btn-secondary"><span class="material-symbols-outlined">edit</span> {{ $transaction->status === TransactionStatus::Rejected ? 'Revisi' : 'Edit' }}</a>
            @endcan
            @can('submit', $transaction)
                <form method="POST" action="{{ route('transactions.submit', $transaction) }}">@csrf
                    <button class="btn-primary"><span class="material-symbols-outlined">send</span> Ajukan Review</button>
                </form>
            @endcan
            @can('decide', $transaction)
                <a href="{{ route('approvals.index', ['id' => $transaction->id]) }}" class="btn-primary"><span class="material-symbols-outlined">fact_check</span> Review Sekarang</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-space-lg">
        <div class="xl:col-span-2 flex flex-col gap-space-lg">
            <section class="glass-card p-space-lg">
                <div class="flex items-start justify-between gap-space-md flex-wrap mb-space-md">
                    <div>
                        <p class="text-label-sm uppercase text-on-surface-variant">Nominal</p>
                        <p class="text-display-lg-mobile sm:text-display-lg text-on-surface">{{ Money::format($transaction->amount) }}</p>
                    </div>
                    <x-status-badge :status="$transaction->status" class="text-label-md!" />
                </div>
                <dl class="grid grid-cols-2 md:grid-cols-4 gap-space-md p-space-md glass-panel">
                    <div><dt class="text-label-sm uppercase text-on-surface-variant">Pengaju (Maker)</dt><dd class="mt-1 flex items-center gap-1"><span class="material-symbols-outlined text-[16px]!">person</span>{{ $transaction->creator->name }}</dd></div>
                    <div><dt class="text-label-sm uppercase text-on-surface-variant">Tanggal</dt><dd class="mt-1">{{ $transaction->trx_date->translatedFormat('d M Y') }}</dd></div>
                    <div><dt class="text-label-sm uppercase text-on-surface-variant">{{ $transaction->type->partyLabel() }}</dt><dd class="mt-1">{{ $transaction->party_name ?: '—' }}</dd></div>
                    <div><dt class="text-label-sm uppercase text-on-surface-variant">{{ $transaction->type->isInvoice() ? 'Jatuh Tempo' : 'Referensi' }}</dt>
                        <dd class="mt-1">{{ $transaction->type->isInvoice() ? ($transaction->due_date?->translatedFormat('d M Y') ?? '—') : ($transaction->reference ?: '—') }}</dd></div>
                    <div><dt class="text-label-sm uppercase text-on-surface-variant">Diajukan</dt><dd class="mt-1">{{ $transaction->submitted_at?->translatedFormat('d M Y H:i') ?? '—' }}</dd></div>
                    @if ($transaction->type->isInvoice())
                        <div><dt class="text-label-sm uppercase text-on-surface-variant">Referensi</dt><dd class="mt-1">{{ $transaction->reference ?: '—' }}</dd></div>
                    @endif
                    <div><dt class="text-label-sm uppercase text-on-surface-variant">Level Otorisasi</dt><dd class="mt-1">{{ $requiresManager ? 'Manager' : 'Supervisor / Manager' }}</dd></div>
                </dl>

                @if ($transaction->rejection_reason && $transaction->status === TransactionStatus::Rejected)
                    <div class="mt-space-md p-space-md rounded-xl bg-error-container/30 ring-1 ring-error/30">
                        <p class="text-label-lg text-on-error-container">Ditolak — alasan:</p>
                        <p class="text-body-md">{{ $transaction->rejection_reason }}</p>
                    </div>
                @endif
            </section>

            <section class="glass-card p-space-lg">
                <div class="flex items-center justify-between mb-space-sm">
                    <h2 class="text-label-lg uppercase text-on-surface-variant">{{ $transaction->journal ? 'Jurnal '.$transaction->journal->ref_no : 'Proyeksi Jurnal Otomatis (Double Entry)' }}</h2>
                    <span class="text-label-sm text-primary flex items-center gap-1"><span class="material-symbols-outlined text-[16px]!">balance</span>Balance (Debit = Kredit)</span>
                </div>
                <table class="data-table">
                    <thead><tr><th>Kode & Nama Akun</th><th class="text-right">Debit (Rp)</th><th class="text-right">Kredit (Rp)</th></tr></thead>
                    <tbody>
                        @if ($transaction->journal)
                            @foreach ($transaction->journal->lines as $line)
                                <tr><td><p class="text-label-lg">{{ $line->account->code }} • {{ $line->account->name }}</p><p class="text-body-sm text-on-surface-variant">{{ $line->description }}</p></td>
                                    <td class="text-right">{{ Money::isPositive($line->debit) ? Money::format($line->debit, false) : '-' }}</td>
                                    <td class="text-right">{{ Money::isPositive($line->credit) ? Money::format($line->credit, false) : '-' }}</td></tr>
                            @endforeach
                        @else
                            <tr><td><p class="text-label-lg">{{ $transaction->debitAccount->code }} • {{ $transaction->debitAccount->name }}</p></td><td class="text-right">{{ Money::format($transaction->amount, false) }}</td><td class="text-right">-</td></tr>
                            <tr><td><p class="text-label-lg">{{ $transaction->creditAccount->code }} • {{ $transaction->creditAccount->name }}</p></td><td class="text-right">-</td><td class="text-right">{{ Money::format($transaction->amount, false) }}</td></tr>
                        @endif
                    </tbody>
                </table>
                @if ($transaction->journal)
                    <div class="mt-space-sm flex items-center justify-between">
                        <x-status-badge :status="$transaction->journal->status" />
                        @can('journal.view')
                            <a href="{{ route('journals.show', $transaction->journal) }}" class="text-label-md text-primary hover:underline">Lihat jurnal →</a>
                        @endcan
                    </div>
                @endif
            </section>
        </div>

        <aside class="flex flex-col gap-space-lg">
            <section class="glass-card p-space-lg">
                <x-card-title icon="attach_file" title="Lampiran Bukti" :subtitle="$transaction->attachments->count().' berkas'" />
                <ul class="space-y-space-sm">
                    @forelse ($transaction->attachments as $att)
                        <li class="glass-panel p-space-sm flex items-center gap-space-sm">
                            <span class="w-10 h-10 rounded-xl bg-secondary-container/50 text-primary flex items-center justify-center shrink-0"><span class="material-symbols-outlined">{{ $att->isPdf() ? 'picture_as_pdf' : 'image' }}</span></span>
                            <div class="min-w-0 flex-1" title="{{ $att->file_name }} — SHA-256: {{ $att->sha256 }}">
                                <p class="text-label-md truncate">{{ $att->file_name }}</p>
                                <p class="text-body-sm text-on-surface-variant truncate">{{ $att->humanSize() }} • SHA-256 <span class="font-mono">{{ substr($att->sha256, 0, 8) }}</span></p>
                            </div>
                            <a href="{{ $att->downloadUrl(true) }}" target="_blank" class="btn-ghost btn-sm" title="Lihat"><span class="material-symbols-outlined text-[18px]!">visibility</span></a>
                            <a href="{{ $att->downloadUrl() }}" class="btn-ghost btn-sm" title="Unduh"><span class="material-symbols-outlined text-[18px]!">download</span></a>
                            @can('update', $transaction)
                                <form method="POST" action="{{ route('attachments.destroy', $att) }}" onsubmit="return confirm('Hapus lampiran ini?')">@csrf @method('DELETE')
                                    <button class="btn-ghost btn-sm text-error!" title="Hapus"><span class="material-symbols-outlined text-[18px]!">delete</span></button>
                                </form>
                            @endcan
                        </li>
                    @empty
                        <li class="text-body-sm text-on-surface-variant">Belum ada lampiran.
                            @if (Money::cmp((string) $transaction->amount, $attachmentThreshold) === 1)<span class="text-primary">Wajib minimal 1 lampiran sebelum diajukan.</span>@endif
                        </li>
                    @endforelse
                </ul>
                @can('update', $transaction)
                    <form method="POST" action="{{ route('transactions.attachments.store', $transaction) }}" enctype="multipart/form-data" class="mt-space-md flex flex-col gap-space-sm">
                        @csrf
                        <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="form-input text-body-sm file:mr-3 file:px-3 file:py-1 file:rounded-lg file:border-0 file:bg-secondary-container file:text-on-secondary-container" required>
                        <button class="btn-secondary btn-sm"><span class="material-symbols-outlined text-[16px]!">upload</span> Unggah Lampiran</button>
                    </form>
                @endcan
            </section>

            <section class="glass-card p-space-lg">
                <x-card-title icon="history" title="Riwayat Approval" />
                <ol class="relative border-s border-primary/15 ms-2 space-y-space-md">
                    @forelse ($transaction->approvals as $ap)
                        <li class="ms-4">
                            <span class="absolute -start-1.5 mt-1.5 w-3 h-3 rounded-full {{ $ap->action->value === 'APPROVE' ? 'bg-primary-container' : 'bg-error' }}"></span>
                            <p class="text-label-md">{{ $ap->action->value === 'APPROVE' ? 'Disetujui' : 'Ditolak' }} oleh {{ $ap->approver->name }}</p>
                            <p class="text-body-sm text-on-surface-variant">{{ $ap->acted_at->translatedFormat('d M Y H:i') }}</p>
                            @if ($ap->note)<p class="text-body-sm mt-1">“{{ $ap->note }}”</p>@endif
                        </li>
                    @empty
                        <li class="ms-4 text-body-sm text-on-surface-variant">Belum ada keputusan.</li>
                    @endforelse
                </ol>
            </section>

            @can('cancel', $transaction)
                <form method="POST" action="{{ route('transactions.cancel', $transaction) }}" class="glass-card p-space-md flex flex-col gap-space-sm" onsubmit="return confirm('Batalkan transaksi ini? Status menjadi CANCELLED dan tidak dapat dikembalikan.')">
                    @csrf
                    <input name="reason" class="form-input" placeholder="Alasan pembatalan (opsional)" maxlength="500">
                    <button class="btn-danger btn-sm"><span class="material-symbols-outlined text-[16px]!">cancel</span> Batalkan Transaksi</button>
                </form>
            @endcan
        </aside>
    </div>
</x-app-layout>
