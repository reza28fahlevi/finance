<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Aplikasi Keuangan
|--------------------------------------------------------------------------
|
| Nilai default dari .env. Sebagian besar dapat ditimpa saat runtime lewat
| menu Pengaturan Umum (tabel `settings`), dibaca via App\Support\Settings.
|
*/

return [

    'company_name' => env('FINANCE_COMPANY_NAME', 'Finance Shafa'),

    'currency' => env('FINANCE_CURRENCY', 'IDR'),

    'attachment' => [
        'disk' => env('FINANCE_ATTACHMENT_DISK', 'local'),
        'mimes' => explode(',', env('FINANCE_ATTACHMENT_MIMES', 'pdf,jpg,jpeg,png')),
        'max_kb' => (int) env('FINANCE_ATTACHMENT_MAX_KB', 5120),
        'required_above' => env('FINANCE_ATTACHMENT_REQUIRED_ABOVE', '1000000'),
        'url_ttl' => (int) env('FINANCE_ATTACHMENT_URL_TTL', 30),
    ],

    'antivirus' => [
        'enabled' => (bool) env('FINANCE_ANTIVIRUS_ENABLED', false),
        'binary' => env('FINANCE_CLAMAV_BINARY', 'clamscan'),
    ],

    'approval' => [
        'tiered' => (bool) env('FINANCE_APPROVAL_TIERED', false),
        'manager_threshold' => env('FINANCE_APPROVAL_MANAGER_THRESHOLD', '10000000'),
    ],

    'numbering' => [
        'journal_format' => env('FINANCE_JOURNAL_REF_FORMAT', 'JV-{YYYY}-{MM}-{SEQ:4}'),
        'transaction_format' => env('FINANCE_TRX_REF_FORMAT', '{TYPE}-{YYYY}-{MM}-{SEQ:4}'),
    ],

    'posting' => [
        'auto_post_on_approve' => (bool) env('FINANCE_AUTO_POST_ON_APPROVE', false),
        'scheduled' => (bool) env('FINANCE_SCHEDULED_POSTING', false),
    ],

];
