<?php

/**
 * Uji konkurensi terhadap database MySQL sungguhan (bukan RefreshDatabase).
 *
 *   php tests/e2e/concurrency.php sequence          # 8 proses × 40 nomor → harus 320 nomor unik
 *   php tests/e2e/concurrency.php posting JV-...    # 5 proses memposting jurnal DRAFT yang sama → 1 sukses
 *
 * Mode "worker-*" dipanggil oleh skrip ini sendiri sebagai proses paralel.
 */

use App\Models\JournalEntry;
use App\Models\User;
use App\Services\PostingService;
use App\Services\SequenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mode = $argv[1] ?? 'sequence';

if ($mode === 'worker-sequence') {
    for ($i = 0; $i < 40; $i++) {
        echo app(SequenceService::class)->next('CONC-{SEQ:4}', now()), "\n";
    }
    exit(0);
}

if ($mode === 'worker-posting') {
    try {
        app(PostingService::class)->post(JournalEntry::where('ref_no', $argv[2])->firstOrFail(), User::where('email', 'akuntan@finance.test')->firstOrFail());
        echo "SUKSES\n";
    } catch (Throwable $e) {
        echo 'DITOLAK: '.$e->getMessage()."\n";
    }
    exit(0);
}

$php = PHP_BINARY;
$self = __FILE__;

if ($mode === 'sequence') {
    DB::table('sequences')->where('key', 'CONC-#')->delete();
    $pool = Process::pool(function ($pool) use ($php, $self) {
        for ($i = 0; $i < 8; $i++) {
            $pool->command([$php, $self, 'worker-sequence']);
        }
    })->start()->wait();

    $numbers = [];
    $errors = 0;
    foreach ($pool as $result) {
        $errors += $result->successful() ? 0 : 1;
        $numbers = array_merge($numbers, preg_grep('/^CONC-\d+$/', explode("\n", trim($result->output()))));
    }
    $unique = count(array_unique($numbers));
    $last = DB::table('sequences')->where('key', 'CONC-#')->value('last_number');
    DB::table('sequences')->where('key', 'CONC-#')->delete();

    printf("Nomor dibuat: %d | unik: %d | duplikat: %d | proses gagal: %d | last_number: %d\n", count($numbers), $unique, count($numbers) - $unique, $errors, $last);
    exit(count($numbers) === 320 && $unique === 320 && $errors === 0 ? 0 : 1);
}

if ($mode === 'posting') {
    $ref = $argv[2] ?? JournalEntry::where('status', 'DRAFT')->value('ref_no');
    if (! $ref) {
        fwrite(STDERR, "Tidak ada jurnal DRAFT.\n");
        exit(1);
    }

    $pool = Process::pool(function ($pool) use ($php, $self, $ref) {
        for ($i = 0; $i < 5; $i++) {
            $pool->command([$php, $self, 'worker-posting', $ref]);
        }
    })->start()->wait();

    $ok = 0;
    foreach ($pool as $result) {
        $line = trim($result->output());
        echo $line, "\n";
        $ok += str_starts_with($line, 'SUKSES') ? 1 : 0;
    }
    $glRows = DB::table('gl_entries')->join('journal_entries', 'journal_entries.id', '=', 'gl_entries.journal_id')->where('ref_no', $ref)->count();
    $lines = DB::table('journal_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_id')->where('ref_no', $ref)->count();

    printf("Jurnal %s: sukses %d/5 | baris GL %d (baris jurnal %d)\n", $ref, $ok, $glRows, $lines);
    exit($ok === 1 && $glRows === $lines ? 0 : 1);
}
