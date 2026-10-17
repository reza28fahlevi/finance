<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Nomor referensi atomik berbasis tabel `sequences` + SELECT ... FOR UPDATE (InnoDB).
 *
 * Format mendukung token: {YYYY}, {YY}, {MM}, {TYPE}, {SEQ:n} (nomor urut n digit).
 * Nomor urut di-reset per kombinasi prefix (mis. per bulan untuk "JV-{YYYY}-{MM}-{SEQ:4}").
 *
 * Penomoran berjalan di koneksi DB terpisah ("sequence") sehingga komit segera dan
 * tidak menunggu transaksi bisnis pemanggil. Akibatnya nomor dapat melompat (gap) bila
 * transaksi bisnis gagal, tetapi tidak pernah duplikat.
 */
class SequenceService
{
    /** Percobaan ulang bila terjadi deadlock/lock wait saat banyak proses membuat nomor bersamaan. */
    private const ATTEMPTS = 5;

    /** @param  array<string, string>  $tokens  Token tambahan, mis. ['TYPE' => 'BKM'] */
    public function next(string $format, CarbonInterface $date, array $tokens = []): string
    {
        $rendered = $this->renderTokens($format, $date, $tokens);

        if (! preg_match('/\{SEQ(?::(\d+))?\}/', $rendered, $match)) {
            $rendered .= '-{SEQ:4}';
            preg_match('/\{SEQ(?::(\d+))?\}/', $rendered, $match);
        }

        $padding = (int) ($match[1] ?? 4);
        $key = str_replace($match[0], '#', $rendered);
        $db = $this->connection();

        $number = $db->transaction(function () use ($db, $key) {
            $row = $db->table('sequences')->where('key', $key)->lockForUpdate()->first();

            if (! $row) {
                // Baris prefix baru: insertOrIgnore aman terhadap race (hanya satu yang berhasil),
                // lalu kunci ulang. Deadlock yang mungkin muncul di sini ditangani dengan retry.
                $db->table('sequences')->insertOrIgnore([
                    'key' => $key,
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = $db->table('sequences')->where('key', $key)->lockForUpdate()->first();
            }

            $next = $row->last_number + 1;
            $db->table('sequences')->where('id', $row->id)->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

            return $next;
        }, self::ATTEMPTS);

        return str_replace($match[0], str_pad((string) $number, $padding, '0', STR_PAD_LEFT), $rendered);
    }

    private function connection(): ConnectionInterface
    {
        $name = config('database.default') === 'mysql' || config('database.default') === 'mariadb' ? 'sequence' : null;

        return DB::connection($name);
    }

    /** @param  array<string, string>  $tokens */
    private function renderTokens(string $format, CarbonInterface $date, array $tokens): string
    {
        $replacements = [
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
        ];

        foreach ($tokens as $name => $value) {
            $replacements['{'.strtoupper($name).'}'] = $value;
        }

        return strtr($format, $replacements);
    }
}
