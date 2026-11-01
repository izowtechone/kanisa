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
        if (! Schema::hasTable('financial_transaction_audits')) {
            Schema::create('financial_transaction_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('financial_transaction_id')->constrained()->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('actor_name')->nullable();
                $table->string('action', 30);
                $table->json('details')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('financial_transaction_audits', ['financial_transaction_id', 'action'])) {
            Schema::table('financial_transaction_audits', function (Blueprint $table): void {
                $table->index(
                    ['financial_transaction_id', 'action'],
                    'fin_tx_audits_tx_action_idx',
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_transaction_audits');
    }
};
