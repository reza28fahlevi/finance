<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->enum('type', ['CASH_IN', 'CASH_OUT', 'PURCHASE_INV', 'SALES_INV']);
            $table->date('trx_date');
            $table->date('due_date')->nullable();
            $table->text('description');
            $table->decimal('amount', 18, 2);
            $table->foreignId('debit_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('credit_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('party_name')->nullable()->comment('Pelanggan / vendor / pihak terkait');
            $table->string('reference')->nullable()->comment('Nomor dokumen eksternal');
            $table->enum('status', ['DRAFT', 'PENDING_REVIEW', 'APPROVED', 'REJECTED', 'JOURNALED', 'CANCELLED'])->default('DRAFT');
            $table->foreignId('period_id')->constrained('accounting_periods')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index(['period_id', 'trx_date']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('file_name')->comment('Nama asli berkas');
            $table->string('storage_path')->comment('Path acak di disk privat');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->char('sha256', 64)->index();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->enum('action', ['APPROVE', 'REJECT']);
            $table->text('note')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('transactions');
    }
};
