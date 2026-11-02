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
        Schema::create('communities', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('location', 150)->nullable();
            $table->foreignId('leader_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'name'], 'communities_status_name_idx');
        });

        Schema::create('community_sequences', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('next_number');
            $table->timestamps();
        });

        DB::table('community_sequences')->insert([
            'id' => 1,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_sequences');
        Schema::dropIfExists('communities');
    }
};
