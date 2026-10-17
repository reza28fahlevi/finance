<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\JournalTemplateController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // 1. Master Data — lihat: semua peran; kelola: Admin
    Route::prefix('master')->name('master.')->group(function () {
        Route::middleware('can:master.view')->group(function () {
            Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
            Route::get('periods', [PeriodController::class, 'index'])->name('periods.index');
            Route::get('templates', [JournalTemplateController::class, 'index'])->name('templates.index');
        });

        Route::middleware('can:master.manage')->group(function () {
            Route::get('accounts/create', [AccountController::class, 'create'])->name('accounts.create');
            Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
            Route::get('accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
            Route::put('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
            Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
            Route::patch('accounts/{account}/toggle', [AccountController::class, 'toggle'])->name('accounts.toggle');

            Route::post('periods', [PeriodController::class, 'store'])->name('periods.store');
            Route::put('templates', [JournalTemplateController::class, 'update'])->name('templates.update');
        });

        Route::post('periods/{period}/close', [PeriodController::class, 'close'])->middleware('can:period.close')->name('periods.close');
        Route::post('periods/{period}/reopen', [PeriodController::class, 'reopen'])->middleware('can:period.reopen')->name('periods.reopen');
    });

    // 2. Transaksi — kelola: Staff Finance (maker)
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [TransactionController::class, 'index'])->name('index');
        Route::get('export', [TransactionController::class, 'export'])->name('export');
        Route::get('create/{type}', [TransactionController::class, 'create'])->name('create');
        Route::post('create/{type}', [TransactionController::class, 'store'])->name('store');
        Route::get('{transaction}', [TransactionController::class, 'show'])->name('show');
        Route::get('{transaction}/edit', [TransactionController::class, 'edit'])->name('edit');
        Route::put('{transaction}', [TransactionController::class, 'update'])->name('update');
        Route::post('{transaction}/submit', [TransactionController::class, 'submit'])->name('submit');
        Route::post('{transaction}/cancel', [TransactionController::class, 'cancel'])->name('cancel');
        Route::post('{transaction}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    });

    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    // Unduh lampiran via URL bertoken (signed, kedaluwarsa) + otorisasi.
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])
        ->middleware('signed')->name('attachments.download');

    // 3. Approval — keputusan: Supervisor/Manager; lihat: Auditor
    Route::prefix('approvals')->name('approvals.')->middleware('can:approval.view')->group(function () {
        Route::get('/', [ApprovalController::class, 'index'])->name('index');
        Route::get('history', [ApprovalController::class, 'history'])->name('history');
        Route::post('{transaction}/approve', [ApprovalController::class, 'approve'])->middleware('can:approval.act')->name('approve');
        Route::post('{transaction}/reject', [ApprovalController::class, 'reject'])->middleware('can:approval.act')->name('reject');
    });

    // 4. Jurnal — kelola & posting: Akuntan
    Route::prefix('journals')->name('journals.')->middleware('can:journal.view')->group(function () {
        Route::get('/', [JournalController::class, 'index'])->name('index');
        Route::get('reversals', [JournalController::class, 'reversals'])->name('reversals');

        Route::middleware('can:journal.manage')->group(function () {
            Route::get('create', [JournalController::class, 'create'])->name('create');
            Route::post('/', [JournalController::class, 'store'])->name('store');
            Route::get('batch', [JournalController::class, 'batch'])->name('batch');
            Route::post('batch', [JournalController::class, 'postBatch'])->name('batch.post');
            Route::get('{journal}/edit', [JournalController::class, 'edit'])->name('edit');
            Route::put('{journal}', [JournalController::class, 'update'])->name('update');
            Route::delete('{journal}', [JournalController::class, 'destroy'])->name('destroy');
            Route::post('{journal}/post', [JournalController::class, 'post'])->name('post');
            Route::post('{journal}/reverse', [JournalController::class, 'reverse'])->name('reverse');
        });

        Route::get('{journal}', [JournalController::class, 'show'])->name('show');
    });

    // 5. General Ledger
    Route::prefix('gl')->name('gl.')->middleware('can:gl.view')->group(function () {
        Route::get('ledger', [LedgerController::class, 'ledger'])->name('ledger');
        Route::get('summary', [LedgerController::class, 'summary'])->name('summary');
        Route::get('trial-balance', [LedgerController::class, 'trialBalance'])->name('trial-balance');
    });

    // 6. Pengaturan
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::middleware('can:users.manage')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
        });

        Route::get('audit-logs', AuditLogController::class)->middleware('can:audit.view')->name('audit');

        Route::middleware('can:settings.manage')->group(function () {
            Route::get('general', [SettingController::class, 'edit'])->name('general');
            Route::put('general', [SettingController::class, 'update'])->name('general.update');
        });
    });
});

require __DIR__.'/auth.php';
