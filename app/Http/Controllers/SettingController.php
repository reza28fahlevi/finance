<?php

namespace App\Http\Controllers;

use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function edit(): View
    {
        return view('settings.general', ['settings' => $this->settings->all()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $format = ['required', 'string', 'max:60', 'regex:/\{SEQ(:\d)?\}/'];

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:100'],
            'journal_ref_format' => $format,
            'transaction_ref_format' => array_merge($format, ['regex:/\{TYPE\}/']),
            'attachment_required_above' => ['required', 'numeric', 'min:0'],
            'attachment_max_kb' => ['required', 'integer', 'min:100', 'max:20480'],
            'approval_manager_threshold' => ['required', 'numeric', 'min:0'],
        ], [
            'journal_ref_format.regex' => 'Format harus memuat token {SEQ} atau {SEQ:n}.',
            'transaction_ref_format.regex' => 'Format nomor transaksi harus memuat token {TYPE} dan {SEQ}/{SEQ:n} agar unik per jenis.',
        ]);

        $data['approval_tiered'] = $request->boolean('approval_tiered');
        $data['auto_post_on_approve'] = $request->boolean('auto_post_on_approve');

        $this->settings->set($data);

        return back()->with('success', 'Pengaturan umum berhasil disimpan.');
    }
}
