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
        Schema::create('financial_sms_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->string('phone', 40);
            $table->string('status', 30)->default('queued');
            $table->boolean('is_test')->default(false);
            $table->string('provider_message_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_sms_logs');
    }
};
