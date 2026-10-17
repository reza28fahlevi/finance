@php use App\Support\Money; @endphp
@php $editing = $transaction->exists; @endphp
<x-app-layout :title="($editing ? 'Edit ' : 'Input ').$type->label()">
    <x-page-header :eyebrow="'Transaksi · '.$type->code()" :context="$editing ? $transaction->number : 'Nomor dibuat otomatis'"
                   :title="($editing ? 'Edit ' : '').$type->label()"
                   :subtitle="$editing && $transaction->status->value === 'REJECTED'
                        ? 'Transaksi ini ditolak reviewer. Setelah diperbaiki, status kembali menjadi Draft dan dapat diajukan ulang.'
                        : 'Isi data transaksi beserta bukti pendukung. Transaksi tersimpan sebagai Draft sampai diajukan untuk review.'">
        <x-slot:actions>
            <a href="{{ $editing ? route('transactions.show', $transaction) : route('transactions.index') }}" class="btn-secondary"><span class="material-symbols-outlined">arrow_back</span> Kembali</a>
        </x-slot:actions>
    </x-page-header>

    @if ($editing && $transaction->rejection_reason)
        <div class="mb-space-lg p-space-md rounded-xl bg-error-container/30 ring-1 ring-error/30 flex gap-space-sm">
            <span class="material-symbols-outlined text-error">feedback</span>
            <div><p class="text-label-lg text-on-error-container">Alasan penolakan</p><p class="text-body-md">{{ $transaction->rejection_reason }}</p></div>
        </div>
    @endif

    @if ($openPeriods->isEmpty())
        <div class="mb-space-lg p-space-md rounded-xl bg-error-container/30 ring-1 ring-error/30 text-body-md">Tidak ada periode akuntansi OPEN. Hubungi Admin untuk membuka periode.</div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('transactions.update', $transaction) : route('transactions.store', $type->slug()) }}"
          class="grid grid-cols-1 xl:grid-cols-3 gap-space-lg"
          x-data="{ amount: '{{ old('amount', $editing ? $transaction->amount : '') }}', threshold: {{ (float) $attachmentThreshold }}, files: [] }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <section class="xl:col-span-2 glass-card p-space-lg">
            <x-card-title :icon="$type->icon()" title="Data Transaksi" :subtitle="$type->label()" />
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                <x-field label="Tanggal Transaksi" name="trx_date" required :hint="'Periode OPEN: '.$openPeriods->pluck('code')->implode(', ')">
                    <input id="trx_date" type="date" name="trx_date" class="form-input" required
                           value="{{ old('trx_date', $transaction->trx_date?->toDateString()) }}"
                           @if ($openPeriods->isNotEmpty()) min="{{ $openPeriods->first()->start_date->toDateString() }}" max="{{ $openPeriods->last()->end_date->toDateString() }}" @endif>
                </x-field>
                @if ($type->isInvoice())
                    <x-field label="Jatuh Tempo" name="due_date">
                        <input id="due_date" type="date" name="due_date" class="form-input" value="{{ old('due_date', $transaction->due_date?->toDateString()) }}">
                    </x-field>
                @else
                    <x-field label="No. Referensi Dokumen" name="reference">
                        <input id="reference" name="reference" class="form-input" value="{{ old('reference', $transaction->reference) }}" maxlength="255" placeholder="Opsional, mis. INV/2026/10/001">
                    </x-field>
                @endif
                <x-field :label="$type->partyLabel()" name="party_name" :required="$type->isInvoice()">
                    <input id="party_name" name="party_name" class="form-input" value="{{ old('party_name', $transaction->party_name) }}" maxlength="255" @required($type->isInvoice())
                           placeholder="{{ $type->isInvoice() ? 'Nama '.strtolower($type->partyLabel()) : 'Opsional' }}">
                </x-field>
                @if ($type->isInvoice())
                    <x-field label="No. Referensi Dokumen" name="reference">
                        <input id="reference" name="reference" class="form-input" value="{{ old('reference', $transaction->reference) }}" maxlength="255" placeholder="No. faktur / PO">
                    </x-field>
                @endif
                <x-field label="Nominal (Rp)" name="amount" required class="{{ $type->isInvoice() ? '' : 'md:col-span-1' }}">
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-label-md">Rp</span>
                        <input id="amount" name="amount" inputmode="decimal" class="form-input pl-10 text-right font-semibold text-body-lg" required x-model="amount" placeholder="0">
                    </div>
                    <p class="mt-1 text-body-sm text-primary" x-text="amount ? formatRupiah(parseAmount(amount)) : ''"></p>
                </x-field>
                <x-field label="Deskripsi" name="description" required class="md:col-span-2">
                    <textarea id="description" name="description" rows="3" class="form-input" required maxlength="1000" placeholder="Uraian transaksi">{{ old('description', $transaction->description) }}</textarea>
                </x-field>
            </div>

            <h3 class="text-label-lg mt-space-lg mb-space-sm flex items-center gap-2"><span class="material-symbols-outlined text-primary">account_tree</span>Akun Terkait</h3>
            <p class="text-body-sm text-on-surface-variant mb-space-md">Diisi otomatis dari Template Jurnal; ubah bila perlu. Jurnal otomatis akan memakai akun ini setelah transaksi disetujui.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                @foreach (['debit_account_id' => $type->debitLabel(), 'credit_account_id' => $type->creditLabel()] as $field => $label)
                    <x-field :label="$label" :name="$field" required>
                        <select id="{{ $field }}" name="{{ $field }}" class="form-input" required>
                            <option value="">— Pilih akun —</option>
                            @foreach ($accounts->groupBy(fn ($a) => $a->type->label()) as $group => $items)
                                <optgroup label="{{ $group }}">
                                    @foreach ($items as $a)
                                        <option value="{{ $a->id }}" @selected((int) old($field, $transaction->{$field}) === $a->id)>{{ $a->code }} — {{ $a->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </x-field>
                @endforeach
            </div>
        </section>

        <aside class="flex flex-col gap-space-lg">
            <section class="glass-card p-space-lg">
                <x-card-title icon="attach_file" title="Lampiran Bukti" :subtitle="'PDF/JPG/PNG, maks. '.number_format($attachmentMaxKb / 1024, 0).' MB per berkas'" />
                <label class="flex flex-col items-center justify-center gap-2 p-space-lg rounded-xl border border-dashed border-primary/30 bg-surface-container-lowest/40 cursor-pointer hover:border-primary-container transition text-center">
                    <span class="material-symbols-outlined text-[32px]! text-primary">cloud_upload</span>
                    <span class="text-label-md">Pilih berkas</span>
                    <span class="text-body-sm text-on-surface-variant">Dapat memilih beberapa berkas</span>
                    <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="sr-only"
                           @change="files = Array.from($event.target.files).map(f => f.name + ' (' + Math.round(f.size / 1024) + ' KB)')">
                </label>
                <ul class="mt-space-sm space-y-1 text-body-sm" x-show="files.length">
                    <template x-for="f in files"><li class="flex items-center gap-1 text-primary"><span class="material-symbols-outlined text-[16px]!">description</span><span x-text="f"></span></li></template>
                </ul>
                @error('attachments.*')<p class="form-error">{{ $message }}</p>@enderror

                @if ($editing && $transaction->attachments->isNotEmpty())
                    <p class="text-label-md text-on-surface-variant mt-space-md mb-1">Lampiran tersimpan</p>
                    <ul class="space-y-1">
                        @foreach ($transaction->attachments as $att)
                            <li class="flex items-center justify-between gap-2 text-body-sm glass-panel px-space-sm py-1.5">
                                <a href="{{ $att->downloadUrl(true) }}" target="_blank" class="truncate text-primary hover:underline">{{ $att->file_name }}</a>
                                <span class="text-on-surface-variant shrink-0">{{ $att->humanSize() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <p class="mt-space-md text-body-sm p-space-sm rounded-lg bg-secondary-container/25 text-on-surface-variant flex gap-2"
                   :class="parseAmount(amount) > threshold ? 'ring-1 ring-primary-container/60 text-on-surface' : ''">
                    <span class="material-symbols-outlined text-[16px]! text-primary">info</span>
                    Nominal di atas {{ Money::format($attachmentThreshold) }} wajib minimal 1 lampiran saat diajukan.
                </p>
            </section>

            <section class="glass-card p-space-lg flex flex-col gap-space-sm">
                <button type="submit" name="action" value="submit" class="btn-primary w-full"><span class="material-symbols-outlined">send</span> Simpan & Ajukan Review</button>
                <button type="submit" name="action" value="draft" class="btn-secondary w-full"><span class="material-symbols-outlined">save</span> Simpan sebagai Draft</button>
                <p class="text-body-sm text-on-surface-variant text-center">Setelah diajukan, transaksi terkunci dari edit sampai diputuskan reviewer.</p>
            </section>
        </aside>
    </form>
</x-app-layout>
