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
        if (Schema::hasTable('bulk_sms_campaigns')) {
            return;
        }

        Schema::create('bulk_sms_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('name', 150);
            $table->text('message');
            $table->string('language', 2);
            $table->string('status', 20)->default('queued');
            $table->string('selection_mode', 20);
            $table->json('selection_criteria')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('invalid_phone_count')->default(0);
            $table->unsignedInteger('sms_segments')->default(0);
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at'], 'bulk_sms_campaigns_status_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_sms_campaigns');
    }
};
