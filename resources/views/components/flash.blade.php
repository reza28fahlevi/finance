@if (session('success') || session('error') || session('batch_results') || $errors->any())
    <div class="mb-space-lg space-y-space-sm" x-data="{ show: true }" x-show="show">
        @foreach (['success' => session('success'), 'error' => session('error')] as $type => $message)
            @if ($message)
                <div role="alert" @class([
                    'flex items-start gap-space-sm p-space-md rounded-xl backdrop-blur-xl',
                    'bg-success-container/40 text-success ring-1 ring-success/20' => $type === 'success',
                    'bg-error-container/40 text-on-error-container ring-1 ring-error/30' => $type === 'error',
                ])>
                    <span class="material-symbols-outlined">{{ $type === 'success' ? 'check_circle' : 'error' }}</span>
                    <p class="flex-1 text-body-md">{{ $message }}</p>
                    <button type="button" class="opacity-70 hover:opacity-100" @click="show = false" aria-label="Tutup">
                        <span class="material-symbols-outlined text-[18px]!">close</span>
                    </button>
                </div>
            @endif
        @endforeach

        @if ($errors->any() && ! session('error'))
            <div role="alert" class="flex items-start gap-space-sm p-space-md rounded-xl bg-error-container/40 text-on-error-container ring-1 ring-error/30">
                <span class="material-symbols-outlined">error</span>
                <div class="flex-1 text-body-md">
                    <p class="font-semibold">Periksa kembali isian Anda:</p>
                    <ul class="list-disc ml-5 mt-1 text-body-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if (session('batch_results'))
            <div class="glass-card p-space-md">
                <p class="text-label-lg mb-space-sm">Hasil Posting Batch</p>
                <ul class="space-y-1 text-body-sm">
                    @foreach (session('batch_results') as $r)
                        <li class="flex items-start gap-2 {{ $r['success'] ? 'text-success' : 'text-error' }}">
                            <span class="material-symbols-outlined text-[16px]!">{{ $r['success'] ? 'check_circle' : 'cancel' }}</span>
                            <span><span class="font-mono font-semibold">{{ $r['ref_no'] }}</span> — {{ $r['message'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
