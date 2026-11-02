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
        Schema::table('members', function (Blueprint $table) {
            $table->index(['status', 'last_name', 'first_name'], 'members_status_name_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('name', 'users_name_idx');
        });

        Schema::table('financial_sms_logs', function (Blueprint $table) {
            $table->index('created_at', 'financial_sms_logs_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex('members_status_name_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_name_idx');
        });

        Schema::table('financial_sms_logs', function (Blueprint $table) {
            $table->dropIndex('financial_sms_logs_created_at_idx');
        });
    }
};
