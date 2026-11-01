<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_number', 40)->unique();
            $table->string('receipt_number', 40)->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->string('payload_hash', 64);
            $table->string('type', 30);
            $table->foreignId('category_id')->nullable()->constrained('finance_categories')->restrictOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('member_number')->nullable();
            $table->string('member_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('financial_transactions')->restrictOnDelete();
            $table->string('envelope_code')->nullable();
            $table->string('payee', 200)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 8);
            $table->string('payment_method', 30);
            $table->string('reference_number')->nullable();
            $table->string('status', 20)->default('pending');
            $table->boolean('is_anonymous')->default(false);
            $table->smallInteger('ledger_sign')->default(1);
            $table->date('transaction_date');
            $table->string('description', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->index(['type', 'status', 'transaction_date']);
            $table->index(['category_id', 'status', 'transaction_date']);
            $table->index(['member_id', 'transaction_date']);
            $table->unique('reversal_of_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
