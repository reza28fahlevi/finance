@php use App\Support\Money; @endphp
<x-app-layout title="Pengaturan Umum">
    <x-page-header eyebrow="Pengaturan" title="Pengaturan Umum" subtitle="Nama perusahaan, format nomor referensi, ambang nominal wajib lampiran/approval bertingkat, dan batas ukuran lampiran. Setiap perubahan tercatat di audit log." />

    <form method="POST" action="{{ route('settings.general.update') }}" class="grid grid-cols-1 lg:grid-cols-2 gap-space-lg items-start">
        @csrf @method('PUT')

        <section class="glass-card p-space-lg space-y-space-md">
            <x-card-title icon="apartment" title="Identitas & Penomoran" />
            <x-field label="Nama Perusahaan" name="company_name" required>
                <input id="company_name" name="company_name" class="form-input" value="{{ old('company_name', $settings['company_name']) }}" required maxlength="100">
            </x-field>
            <x-field label="Format Nomor Jurnal" name="journal_ref_format" required hint="Token: {YYYY} {YY} {MM} {SEQ:n}. Contoh: JV-{YYYY}-{MM}-{SEQ:4} → JV-2026-10-0001">
                <input id="journal_ref_format" name="journal_ref_format" class="form-input font-mono" value="{{ old('journal_ref_format', $settings['journal_ref_format']) }}" required>
            </x-field>
            <x-field label="Format Nomor Transaksi" name="transaction_ref_format" required hint="Token tambahan {TYPE} = BKM / BKK / INV-IN / INV-OUT. Contoh: {TYPE}-{YYYY}-{MM}-{SEQ:4} → BKK-2026-10-0001">
                <input id="transaction_ref_format" name="transaction_ref_format" class="form-input font-mono" value="{{ old('transaction_ref_format', $settings['transaction_ref_format']) }}" required>
            </x-field>
            <p class="text-body-sm text-on-surface-variant">Nomor urut di-reset otomatis per kombinasi prefix (mis. per bulan) dan dibuat atomik sehingga tidak ada duplikat.</p>
        </section>

        <section class="glass-card p-space-lg space-y-space-md">
            <x-card-title icon="rule" title="Kontrol Transaksi & Approval" />
            <x-field label="Ambang Nominal Wajib Lampiran (Rp)" name="attachment_required_above" required :hint="'Saat ini: transaksi di atas '.Money::format($settings['attachment_required_above']).' wajib minimal 1 lampiran.'">
                <input id="attachment_required_above" name="attachment_required_above" type="number" step="0.01" min="0" class="form-input" value="{{ old('attachment_required_above', $settings['attachment_required_above']) }}" required>
            </x-field>
            <x-field label="Batas Ukuran Lampiran (KB)" name="attachment_max_kb" required hint="Default 5120 KB (5 MB) per berkas.">
                <input id="attachment_max_kb" name="attachment_max_kb" type="number" min="100" max="20480" class="form-input" value="{{ old('attachment_max_kb', $settings['attachment_max_kb']) }}" required>
            </x-field>
            <label class="glass-panel p-space-md flex items-start gap-2 cursor-pointer">
                <input type="hidden" name="approval_tiered" value="0">
                <input type="checkbox" name="approval_tiered" value="1" class="form-check mt-0.5" @checked(old('approval_tiered', $settings['approval_tiered']))>
                <span><span class="text-label-lg block">Approval Bertingkat</span><span class="text-body-sm text-on-surface-variant">Nominal ≥ ambang di bawah hanya dapat disetujui Manager; di bawahnya cukup Supervisor.</span></span>
            </label>
            <x-field label="Ambang Approval Manager (Rp)" name="approval_manager_threshold" required>
                <input id="approval_manager_threshold" name="approval_manager_threshold" type="number" step="0.01" min="0" class="form-input" value="{{ old('approval_manager_threshold', $settings['approval_manager_threshold']) }}" required>
            </x-field>
            <label class="glass-panel p-space-md flex items-start gap-2 cursor-pointer">
                <input type="hidden" name="auto_post_on_approve" value="0">
                <input type="checkbox" name="auto_post_on_approve" value="1" class="form-check mt-0.5" @checked(old('auto_post_on_approve', $settings['auto_post_on_approve']))>
                <span><span class="text-label-lg block">Posting Otomatis saat Approve</span><span class="text-body-sm text-on-surface-variant">Jurnal otomatis langsung diposting ke GL begitu transaksi disetujui. Bila nonaktif, Akuntan memposting manual / via Posting Batch.</span></span>
            </label>
        </section>

        <div class="lg:col-span-2"><button class="btn-primary"><span class="material-symbols-outlined">save</span> Simpan Pengaturan</button></div>
    </form>
</x-app-layout>
