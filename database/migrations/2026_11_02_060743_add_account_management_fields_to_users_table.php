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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable();
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status', 20)->default('active');
            }

            if (! Schema::hasColumn('users', 'member_id')) {
                $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
        });

        DB::table('users')->whereNull('status')->update(['status' => 'active']);

        Schema::table('members', function (Blueprint $table): void {
            if (! Schema::hasColumn('members', 'sms_opt_in')) {
                $table->boolean('sms_opt_in')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'member_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('member_id');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach (['phone', 'status', 'last_login_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasColumn('members', 'sms_opt_in')) {
            Schema::table('members', function (Blueprint $table): void {
                $table->dropColumn('sms_opt_in');
            });
        }
    }
};
