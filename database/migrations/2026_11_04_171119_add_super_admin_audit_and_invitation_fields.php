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
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('password_reset_requested_by')
                ->nullable()
                ->after('last_login_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('password_reset_requested_at')->nullable()->after('password_reset_requested_by');
        });

        Schema::table('financial_sms_logs', function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->nullable()
                ->after('member_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['user_id', 'notification_type', 'created_at'], 'financial_sms_logs_user_type_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_sms_logs', function (Blueprint $table): void {
            $table->dropIndex('financial_sms_logs_user_type_created_index');
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('password_reset_requested_by');
            $table->dropColumn('password_reset_requested_at');
        });
    }
};
