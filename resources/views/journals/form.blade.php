@php $editing = $journal->exists; @endphp
<x-app-layout :title="$editing ? 'Edit Jurnal' : 'Jurnal Manual'">
    <form method="POST" action="{{ $editing ? route('journals.update', $journal) : route('journals.store') }}"
          x-data="journalForm(@js(array_values($initialLines)))">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-page-header eyebrow="Modul Akuntansi Buku Besar" context="Audit Trail" :title="$editing ? 'Edit Jurnal '.$journal->ref_no : 'Entri Jurnal Manual'"
                       subtitle="Penyusunan jurnal penyesuaian, akrual, atau koreksi dengan validasi keseimbangan debit–kredit otomatis.">
            <x-slot:actions>
                <a href="{{ route('journals.index') }}" class="btn-secondary"><span class="material-symbols-outlined">list</span> Daftar Jurnal</a>
                <button type="submit" name="action" value="draft" class="btn-secondary" :disabled="!balanced"><span class="material-symbols-outlined">save</span> Simpan sebagai Draft</button>
                <button type="submit" name="action" value="post" class="btn-primary" :disabled="!balanced"><span class="material-symbols-outlined">check_circle</span> Simpan & Posting</button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-space-lg mb-space-lg">
            <section class="xl:col-span-2 glass-card p-space-lg">
                <x-card-title icon="description" title="Data Header Jurnal" subtitle="Identifikasi jurnal dan klasifikasi pembukuan umum" />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                    <div>
                        <label class="form-label flex justify-between">No. Referensi Voucher <span class="text-primary">AUTO GENERATED</span></label>
                        <div class="form-input flex items-center gap-2 text-on-surface-variant"><span class="material-symbols-outlined text-[18px]!">tag</span><span class="font-mono">{{ $nextRef }}</span></div>
                    </div>
                    <x-field label="Tanggal Efektif Jurnal" name="entry_date" required :hint="'Periode OPEN: '.($openPeriods->pluck('code')->implode(', ') ?: 'tidak ada')">
                        <input id="entry_date" type="date" name="entry_date" class="form-input" required value="{{ old('entry_date', $journal->entry_date?->toDateString()) }}"
                               @if ($openPeriods->isNotEmpty()) min="{{ $openPeriods->first()->start_date->toDateString() }}" max="{{ $openPeriods->last()->end_date->toDateString() }}" @endif>
                    </x-field>
                    <x-field label="Keterangan Memo / Narasi Utama" name="description" required class="md:col-span-2">
                        <textarea id="description" name="description" rows="2" class="form-input" required maxlength="1000" placeholder="Mis. Penyesuaian beban sewa dibayar dimuka Oktober 2026">{{ old('description', $journal->description) }}</textarea>
                    </x-field>
                </div>
            </section>

            <section class="glass-card p-space-lg flex flex-col gap-space-md">
                <x-card-title icon="verified_user" title="Persetujuan & Otoritas" subtitle="Jurnal manual disusun & diposting oleh Akuntan" class="mb-0!" />
                <div class="glass-panel p-space-md flex items-center gap-space-sm">
                    <span class="w-10 h-10 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center text-label-md">{{ auth()->user()->initials() }}</span>
                    <div><p class="text-label-lg">{{ auth()->user()->name }}</p><p class="text-body-sm text-on-surface-variant">{{ auth()->user()->role->label() }}</p></div>
                </div>
                <div class="p-space-sm rounded-xl bg-surface-container-lowest/50">
                    <p class="text-label-sm text-on-surface-variant flex justify-between">Kesiapan Integritas Data <span :class="balanced ? 'text-primary' : 'text-error'" x-text="balanced ? '100% Balanced' : 'Belum seimbang'"></span></p>
                    <div class="mt-1 h-1.5 rounded-full bg-surface-container-highest overflow-hidden"><div class="h-full bg-gradient-to-r from-primary-container to-secondary transition-all" :style="`width: ${balanced ? 100 : (Math.max(totalDebit, totalCredit) ? Math.round(Math.min(totalDebit, totalCredit) / Math.max(totalDebit, totalCredit) * 100) : 0)}%`"></div></div>
                </div>
            </section>
        </div>

        {{-- Indikator selisih debit–kredit real-time --}}
        <section class="glass-card p-space-lg mb-space-lg grid grid-cols-1 lg:grid-cols-2 gap-space-lg items-center"
                 :class="balanced ? '' : 'ring-1 ring-error/30'">
            <div class="flex items-center gap-space-md">
                <span class="w-14 h-14 rounded-2xl flex items-center justify-center" :class="balanced ? 'bg-primary-container/25 text-primary' : 'bg-error-container/40 text-error'">
                    <span class="material-symbols-outlined text-[28px]!">balance</span>
                </span>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-headline-sm">Validasi Integritas Ledger</h2>
                        <span class="badge badge-dot" :class="balanced ? 'bg-primary-container/20 text-primary' : 'bg-error-container/50 text-on-error-container'" x-text="balanced ? 'Seimbang (Balanced)' : 'Tidak Seimbang'"></span>
                    </div>
                    <p class="text-body-sm text-on-surface-variant" x-text="balanced ? 'Debit dan kredit cocok presisi. Jurnal siap disimpan dan diposting.' : 'Jurnal tidak dapat disimpan sampai total debit sama dengan total kredit.'"></p>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-space-md p-space-md rounded-2xl bg-surface-container-lowest/60">
                <div><p class="text-label-sm uppercase text-on-surface-variant">Total Debit</p><p class="text-headline-sm font-bold" x-text="money(totalDebit)"></p></div>
                <div><p class="text-label-sm uppercase text-on-surface-variant">Total Kredit</p><p class="text-headline-sm font-bold" x-text="money(totalCredit)"></p></div>
                <div><p class="text-label-sm uppercase text-on-surface-variant">Selisih (Variance)</p><p class="text-headline-sm font-bold" :class="difference === 0 ? 'text-primary-container' : 'text-error'" x-text="money(Math.abs(difference))"></p></div>
            </div>
        </section>

        <section class="glass-card p-space-lg">
            <x-card-title icon="table_rows" title="Rincian Baris Jurnal" subtitle="Masukkan alokasi akun lawan sesuai kaidah pembukuan ganda (isi debit ATAU kredit per baris)">
                <x-slot:actions>
                    <button type="button" class="btn-secondary btn-sm" @click="addLine()"><span class="material-symbols-outlined text-[18px]!">add</span> Tambah Baris Akun</button>
                </x-slot:actions>
            </x-card-title>
            <div class="overflow-x-auto">
                <table class="data-table min-w-[48rem]">
                    <thead><tr><th class="w-10">No</th><th>Kode & Nama Akun</th><th>Deskripsi Baris / Uraian</th><th class="text-right w-44">Debit (Rp)</th><th class="text-right w-44">Kredit (Rp)</th><th class="w-12 text-center">Aksi</th></tr></thead>
                    <tbody>
                        <template x-for="(line, i) in lines" :key="i">
                            <tr>
                                <td class="text-on-surface-variant" x-text="i + 1"></td>
                                <td>
                                    <select class="form-input" :name="`lines[${i}][account_id]`" x-model="line.account_id" :required="!!(line.debit || line.credit)">
                                        <option value="">— Pilih akun —</option>
                                        @foreach ($accounts as $a)
                                            <option value="{{ $a->id }}">{{ $a->code }} | {{ $a->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input class="form-input" :name="`lines[${i}][description]`" x-model="line.description" maxlength="255" placeholder="Uraian"></td>
                                <td><input class="form-input text-right font-mono" inputmode="decimal" :name="`lines[${i}][debit]`" x-model="line.debit" @input="if (line.debit) line.credit = ''" placeholder="0"></td>
                                <td><input class="form-input text-right font-mono" inputmode="decimal" :name="`lines[${i}][credit]`" x-model="line.credit" @input="if (line.credit) line.debit = ''" placeholder="0"></td>
                                <td class="text-center"><button type="button" class="btn-ghost btn-sm" @click="removeLine(i)" :disabled="lines.length <= 2" title="Hapus baris"><span class="material-symbols-outlined text-[18px]!">delete</span></button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="mt-space-md flex items-center justify-between gap-space-md flex-wrap">
                <p class="text-body-sm text-on-surface-variant flex items-center gap-1"><span class="material-symbols-outlined text-[16px]!">lightbulb</span>Gunakan titik untuk desimal (mis. 1500000.50). Satu baris hanya boleh berisi debit atau kredit.</p>
                <button type="button" class="btn-secondary btn-sm" @click="addLine()"><span class="material-symbols-outlined text-[18px]!">add_circle</span> Tambah Baris Akun Baru</button>
            </div>
        </section>
    </form>
</x-app-layout>
