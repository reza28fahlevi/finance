<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan Umum: nilai di tabel `settings` menimpa default dari config/finance.php.
 */
class Settings
{
    /** Kunci pengaturan => [path config default, tipe]. */
    public const DEFINITIONS = [
        'company_name' => ['finance.company_name', 'string'],
        'journal_ref_format' => ['finance.numbering.journal_format', 'string'],
        'transaction_ref_format' => ['finance.numbering.transaction_format', 'string'],
        'attachment_required_above' => ['finance.attachment.required_above', 'decimal'],
        'attachment_max_kb' => ['finance.attachment.max_kb', 'int'],
        'approval_tiered' => ['finance.approval.tiered', 'bool'],
        'approval_manager_threshold' => ['finance.approval.manager_threshold', 'decimal'],
        'auto_post_on_approve' => ['finance.posting.auto_post_on_approve', 'bool'],
    ];

    private const CACHE_KEY = 'finance.settings';

    public function get(string $key): mixed
    {
        if (! array_key_exists($key, self::DEFINITIONS)) {
            throw new \InvalidArgumentException("Pengaturan tidak dikenal: {$key}");
        }

        [$configPath, $type] = self::DEFINITIONS[$key];
        $stored = $this->stored();
        $value = array_key_exists($key, $stored) ? $stored[$key] : config($configPath);

        return $this->cast($value, $type);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return collect(array_keys(self::DEFINITIONS))
            ->mapWithKeys(fn (string $key) => [$key => $this->get($key)])
            ->all();
    }

    /** @param  array<string, mixed>  $values */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFINITIONS)) {
                continue;
            }
            $type = self::DEFINITIONS[$key][1];
            $stored = $type === 'bool' ? ($value ? '1' : '0') : (string) $value;

            $setting = Setting::firstOrNew(['key' => $key]);
            $setting->value = $stored;
            $setting->save();
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, string|null> */
    private function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return Setting::pluck('value', 'key')->all();
        });
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'decimal' => number_format((float) $value, 2, '.', ''),
            default => (string) $value,
        };
    }
}
