@php use App\Enums\TransactionType; @endphp
<x-app-layout title="Template Jurnal Otomatis">
    <x-page-header eyebrow="Master Data" context="Auto Journal" title="Template Jurnal Otomatis"
                   subtitle="Pemetaan jenis transaksi ke akun debit/kredit default. Nilai ini menjadi isian awal form transaksi dan dipakai saat jurnal otomatis diterbitkan setelah approval." />

    <form method="POST" action="{{ route('master.templates.update') }}" class="flex flex-col gap-space-md">
        @csrf @method('PUT')
        @php $canManage = auth()->user()->can('master.manage'); @endphp

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-space-md">
            @foreach (TransactionType::cases() as $type)
                @php $tpl = $templates->get($type->value); @endphp
                <section class="glass-card p-space-lg">
                    <x-card-title :icon="$type->icon()" :title="$type->label()" :subtitle="'Kode nomor: '.$type->code()" />
                    <div class="grid grid-cols-1 gap-space-md">
                        @foreach (['debit' => $type->debitLabel(), 'credit' => $type->creditLabel()] as $side => $label)
                            @php $field = "templates[{$type->value}][{$side}_account_id]"; $current = old("templates.{$type->value}.{$side}_account_id", $tpl?->{$side.'_account_id'}); @endphp
                            <x-field :label="$label" :name="$field">
                                <select id="{{ $field }}" name="{{ $field }}" class="form-input" @disabled(! $canManage) required>
                                    <option value="">— Pilih akun —</option>
                                    @foreach ($accounts as $a)
                                        <option value="{{ $a->id }}" @selected((int) $current === $a->id)>{{ $a->code }} — {{ $a->name }}</option>
                                    @endforeach
                                </select>
                            </x-field>
                        @endforeach
                    </div>
                    <div class="mt-space-md p-space-sm rounded-xl bg-surface-container-lowest/50 text-body-sm font-mono flex flex-col gap-1">
                        <span class="text-on-surface-variant">Proyeksi jurnal:</span>
                        <span>Dr {{ $tpl?->debitAccount?->label() ?? '—' }}</span>
                        <span class="pl-6">Cr {{ $tpl?->creditAccount?->label() ?? '—' }}</span>
                    </div>
                </section>
            @endforeach
        </div>

        @if ($canManage)
            <div><button class="btn-primary"><span class="material-symbols-outlined">save</span> Simpan Template</button></div>
        @endif
    </form>
</x-app-layout>
