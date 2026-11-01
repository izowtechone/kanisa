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
            $table->foreignId('campaign_id')
                ->nullable()
                ->after('category_id')
                ->constrained('financial_campaigns')
                ->restrictOnDelete();
            $table->index(['campaign_id', 'status', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->dropIndex(['campaign_id', 'status', 'transaction_date']);
            $table->dropConstrainedForeignId('campaign_id');
        });
    }
};
