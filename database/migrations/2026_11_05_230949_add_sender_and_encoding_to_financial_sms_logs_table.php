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
        Schema::table('financial_sms_logs', function (Blueprint $table): void {
            $table->string('sender_id', 20)->nullable();
            $table->string('encoding', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_sms_logs', function (Blueprint $table): void {
            $table->dropColumn(['sender_id', 'encoding']);
        });
    }
};
