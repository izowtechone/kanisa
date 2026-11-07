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
        Schema::table('financial_settings', function (Blueprint $table): void {
            $table->longText('value')->nullable()->change();
        });

        Schema::table('financial_sms_logs', function (Blueprint $table): void {
            $table->string('sender_id', 32)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Sender storage cannot be narrowed safely; use a forward migration.');
    }
};
