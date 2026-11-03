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
        Schema::table('finance_categories', function (Blueprint $table): void {
            $table->string('name_en', 150)->nullable()->after('name');
            $table->string('name_sw', 150)->nullable()->after('name_en');
            $table->unique(['type', 'name_en'], 'finance_categories_type_name_en_unique');
            $table->unique(['type', 'name_sw'], 'finance_categories_type_name_sw_unique');
        });

        // Existing names are the English baseline; Swahili stays empty and falls back to `name` until edited.
        DB::table('finance_categories')
            ->whereNull('name_en')
            ->orderBy('id')
            ->each(function (object $category): void {
                if (mb_strlen($category->name) <= 150) {
                    DB::table('finance_categories')->where('id', $category->id)->update(['name_en' => $category->name]);
                }
            });
    }

    public function down(): void
    {
        throw new LogicException('Offering category translations contain administrator data and cannot be discarded by rollback.');
    }
};
