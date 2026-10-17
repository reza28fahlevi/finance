<?php

use App\Enums\JournalStatus;
use App\Enums\Role;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\PostingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 * Posting batch semua jurnal DRAFT pada periode OPEN.
 * Dijalankan atas nama akun Akuntan aktif (default: yang pertama) agar posted_by tetap tercatat.
 */
Artisan::command('finance:post-batch {--user= : Email akuntan yang tercatat sebagai pemosting}', function (PostingService $posting) {
    $user = $this->option('user')
        ? User::where('email', $this->option('user'))->first()
        : User::where('role', Role::Accountant)->where('is_active', true)->orderBy('id')->first();

    if (! $user || ! $user->hasPermission('journal.manage')) {
        $this->error('Akun akuntan aktif tidak ditemukan.');

        return 1;
    }

    $ids = JournalEntry::where('status', JournalStatus::Draft)
        ->whereHas('period', fn ($q) => $q->where('status', 'OPEN'))
        ->orderBy('entry_date')->orderBy('id')
        ->pluck('id');

    if ($ids->isEmpty()) {
        $this->info('Tidak ada jurnal DRAFT untuk diposting.');

        return 0;
    }

    $results = $posting->postBatch($ids, $user);
    foreach ($results as $r) {
        $this->line(($r['success'] ? '<info>OK</info>   ' : '<error>GAGAL</error> ').$r['ref_no'].' — '.$r['message']);
    }

    return collect($results)->contains('success', false) ? 1 : 0;
})->purpose('Posting batch jurnal DRAFT ke General Ledger');

// Opsional: aktifkan dengan FINANCE_SCHEDULED_POSTING=true + 1 entri cron "* * * * * php artisan schedule:run".
if (config('finance.posting.scheduled')) {
    Schedule::command('finance:post-batch')->dailyAt('23:30')->withoutOverlapping();
}
