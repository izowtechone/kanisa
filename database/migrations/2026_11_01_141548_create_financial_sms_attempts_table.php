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
        if (! Schema::hasTable('financial_sms_attempts')) {
            Schema::create('financial_sms_attempts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('financial_sms_log_id')->constrained()->restrictOnDelete();
                $table->unsignedSmallInteger('attempt_number');
                $table->string('status', 20);
                $table->string('provider_message_id')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('financial_sms_attempts', ['financial_sms_log_id', 'attempt_number'], 'unique')) {
            Schema::table('financial_sms_attempts', function (Blueprint $table): void {
                $table->unique(
                    ['financial_sms_log_id', 'attempt_number'],
                    'fin_sms_attempt_log_number_unique',
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_sms_attempts');
    }
};
