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
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->string('source', 30)->default('finance');
            $table->index(
                ['source', 'type', 'status', 'transaction_date'],
                'fin_tx_source_type_status_date_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->dropIndex('fin_tx_source_type_status_date_idx');
            $table->dropColumn('source');
        });
    }
};
