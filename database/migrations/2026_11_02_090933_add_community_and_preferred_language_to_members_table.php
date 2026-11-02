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
        Schema::table('members', function (Blueprint $table): void {
            $table->foreignId('community_id')->nullable()->constrained()->nullOnDelete();
            $table->string('preferred_language', 2)->nullable();
            $table->index(['community_id', 'status', 'last_name', 'first_name'], 'members_community_status_name_idx');
            $table->index('phone', 'members_phone_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropIndex('members_community_status_name_idx');
            $table->dropIndex('members_phone_idx');
            $table->dropConstrainedForeignId('community_id');
            $table->dropColumn('preferred_language');
        });
    }
};
