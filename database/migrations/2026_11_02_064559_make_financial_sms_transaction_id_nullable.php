<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('financial_sms_logs', function (Blueprint $table): void {
            $table->foreignId('financial_transaction_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('financial_sms_logs')->whereNull('financial_transaction_id')->exists()) {
            throw new RuntimeException(
                'Financial SMS logs without a transaction must be removed or migrated before this rollback.',
            );
        }

        Schema::table('financial_sms_logs', function (Blueprint $table): void {
            $table->foreignId('financial_transaction_id')->nullable(false)->change();
        });
    }
};
