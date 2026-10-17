<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pemeriksaan kesiapan server: php artisan finance:check
 * Exit code 0 bila semua requirement WAJIB terpenuhi.
 */
class CheckRequirements extends Command
{
    protected $signature = 'finance:check';

    protected $description = 'Periksa requirement server untuk instalasi Finance Shafa';

    private const REQUIRED_EXTENSIONS = [
        'pdo_mysql' => 'koneksi MySQL',
        'bcmath' => 'aritmetika uang presisi',
        'intl' => 'format tanggal & bahasa Indonesia',
        'mbstring' => 'string multibyte',
        'fileinfo' => 'deteksi tipe berkas lampiran dari isi',
        'openssl' => 'enkripsi & session',
        'tokenizer' => 'Laravel',
        'xml' => 'Laravel',
        'ctype' => 'Laravel',
        'dom' => 'Laravel',
        'session' => 'Laravel',
    ];

    private const RECOMMENDED_EXTENSIONS = [
        'opcache' => 'performa produksi',
        'curl' => 'HTTP client',
        'zip' => 'Composer',
    ];

    private int $failures = 0;

    public function handle(): int
    {
        $this->line('<options=bold>Finance Shafa — pemeriksaan requirement</>');

        $this->section('PHP');
        $this->check('Versi PHP ≥ 8.3', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION);
        foreach (self::REQUIRED_EXTENSIONS as $ext => $why) {
            $this->check("ext-{$ext} ({$why})", extension_loaded($ext));
        }
        foreach (self::RECOMMENDED_EXTENSIONS as $ext => $why) {
            $this->check("ext-{$ext} ({$why})", extension_loaded($ext), null, false);
        }

        $upload = $this->bytes(ini_get('upload_max_filesize'));
        $post = $this->bytes(ini_get('post_max_size'));
        $this->check('upload_max_filesize ≥ 5M (lampiran 5 MB)', $upload >= 5 * 1024 * 1024, ini_get('upload_max_filesize'));
        $this->check('post_max_size ≥ upload_max_filesize', $post >= $upload, ini_get('post_max_size'));
        $this->check('memory_limit ≥ 128M', in_array(ini_get('memory_limit'), ['-1'], true) || $this->bytes(ini_get('memory_limit')) >= 128 * 1024 * 1024, ini_get('memory_limit'), false);

        $this->section('Konfigurasi aplikasi (.env)');
        $this->check('File .env ada', file_exists(base_path('.env')));
        $this->check('APP_KEY terisi', filled(config('app.key')));
        $this->check('Timezone', true, config('app.timezone'), false);
        if (app()->isProduction()) {
            $this->check('APP_DEBUG=false di production', ! config('app.debug'));
        }
        $this->check('SESSION_DRIVER=database (tanpa Redis)', config('session.driver') === 'database', config('session.driver'), false);

        $this->section('Database');
        try {
            $pdo = DB::connection()->getPdo();
            $version = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $this->check('Koneksi ke database "'.config('database.connections.mysql.database').'"', true, $version);
            $isMaria = str_contains(strtolower($version), 'mariadb');
            preg_match('/(\d+\.\d+\.\d+)/', $version, $m);
            $this->check($isMaria ? 'MariaDB ≥ 10.4 (CHECK constraint)' : 'MySQL ≥ 8.0.16 (CHECK constraint)', version_compare($m[1] ?? '0', $isMaria ? '10.4.0' : '8.0.16', '>='), $m[1] ?? $version);
            DB::connection('sequence')->getPdo();
            $this->check('Koneksi "sequence" (penomoran)', true);
            $migrated = Schema::hasTable('role_permissions') && Schema::hasTable('journal_entries');
            $this->check('Migrasi sudah dijalankan', $migrated, $migrated ? null : 'jalankan: php artisan migrate --seed');
        } catch (Throwable $e) {
            $this->check('Koneksi ke database', false, $e->getMessage());
        }

        $this->section('Berkas & folder');
        foreach (['storage', 'storage/app/private', 'storage/framework', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $this->check("{$dir} dapat ditulis", is_dir(base_path($dir)) && is_writable(base_path($dir)));
        }
        $this->check('Aset front-end sudah di-build (public/build/manifest.json)', file_exists(public_path('build/manifest.json')), file_exists(public_path('build/manifest.json')) ? null : 'jalankan: npm ci && npm run build');
        $this->check('vendor/ terpasang', file_exists(base_path('vendor/autoload.php')));

        if (config('finance.antivirus.enabled')) {
            $this->section('Antivirus');
            exec(escapeshellarg(config('finance.antivirus.binary')).' --version 2>&1', $out, $code);
            $this->check('ClamAV tersedia', $code === 0, $out[0] ?? null);
        }

        $this->newLine();
        if ($this->failures > 0) {
            $this->error("{$this->failures} requirement wajib belum terpenuhi.");

            return self::FAILURE;
        }
        $this->info('Semua requirement wajib terpenuhi.');

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<fg=magenta;options=bold>{$title}</>");
    }

    private function check(string $label, bool $ok, ?string $detail = null, bool $required = true): void
    {
        $mark = $ok ? '<fg=green>✔</>' : ($required ? '<fg=red>✘</>' : '<fg=yellow>!</>');
        $this->line("  {$mark} {$label}".($detail !== null && $detail !== '' ? " <fg=gray>({$detail})</>" : '').(! $ok && ! $required ? ' <fg=yellow>[disarankan]</>' : ''));

        if (! $ok && $required) {
            $this->failures++;
        }
    }

    private function bytes(string|false $value): int
    {
        $value = trim((string) $value);
        $unit = strtolower(substr($value, -1));
        $num = (int) $value;

        return match ($unit) {
            'g' => $num * 1024 ** 3,
            'm' => $num * 1024 ** 2,
            'k' => $num * 1024,
            default => $num,
        };
    }
}
