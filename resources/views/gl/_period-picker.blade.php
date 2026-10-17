{{-- Pemilih periode untuk halaman GL. Variabel: $periods, $period, $route, $extra (opsional) --}}
<form method="GET" action="{{ route($route) }}" class="glass-card p-space-md mb-space-lg flex items-end gap-space-md flex-wrap">
    @foreach (($extra ?? []) as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    {{ $slot ?? '' }}
    <div>
        <label class="form-label flex items-center gap-1" for="period"><span class="material-symbols-outlined text-[16px]!">calendar_month</span>Periode Akuntansi</label>
        <select id="period" name="period" class="form-input w-56" onchange="this.form.submit()">
            @foreach ($periods as $p)
                <option value="{{ $p->id }}" @selected($period?->id === $p->id)>{{ $p->code }} — {{ $p->label() }} ({{ $p->status->value }})</option>
            @endforeach
        </select>
    </div>
</form>
