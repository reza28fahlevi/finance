<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->enum('type', ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'EXPENSE']);
            $table->enum('normal_balance', ['DEBIT', 'CREDIT']);
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_cash')->default(false)->comment('Akun kas/bank untuk ringkasan dashboard');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 7)->unique()->comment('YYYY-MM');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['OPEN', 'CLOSED'])->default('OPEN');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });

        // Penomoran atomik: satu baris per prefix (mis. "JV-2026-10"), dikunci dengan SELECT ... FOR UPDATE.
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Template jurnal otomatis: jenis transaksi -> akun debit/kredit default.
        Schema::create('journal_templates', function (Blueprint $table) {
            $table->id();
            $table->enum('transaction_type', ['CASH_IN', 'CASH_OUT', 'PURCHASE_INV', 'SALES_INV'])->unique();
            $table->foreignId('debit_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('credit_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('description_template')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_templates');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('sequences');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounts');
    }
};
