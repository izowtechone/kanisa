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
        Schema::table('financial_sms_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('financial_sms_logs', 'bulk_sms_campaign_id')) {
                $table->foreignId('bulk_sms_campaign_id')->nullable();
            }

            if (! Schema::hasColumn('financial_sms_logs', 'member_id')) {
                $table->foreignId('member_id')->nullable();
            }

            if (! Schema::hasColumn('financial_sms_logs', 'sms_segments')) {
                $table->unsignedSmallInteger('sms_segments')->default(1);
            }
        });

        if (! Schema::hasForeignKey('financial_sms_logs', ['bulk_sms_campaign_id'])) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->foreign('bulk_sms_campaign_id', 'financial_sms_logs_campaign_fk')
                    ->references('id')
                    ->on('bulk_sms_campaigns')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasForeignKey('financial_sms_logs', ['member_id'])) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->foreign('member_id', 'financial_sms_logs_member_fk')
                    ->references('id')
                    ->on('members')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasIndex(
            'financial_sms_logs',
            ['bulk_sms_campaign_id', 'member_id'],
            'unique',
        )) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->unique(
                    ['bulk_sms_campaign_id', 'member_id'],
                    'financial_sms_logs_campaign_member_unique',
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex(
            'financial_sms_logs',
            ['bulk_sms_campaign_id', 'member_id'],
            'unique',
        )) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->dropUnique('financial_sms_logs_campaign_member_unique');
            });
        }

        Schema::table('financial_sms_logs', function (Blueprint $table) {
            if (Schema::hasForeignKey('financial_sms_logs', ['bulk_sms_campaign_id'])) {
                $table->dropForeign('financial_sms_logs_campaign_fk');
            }

            if (Schema::hasForeignKey('financial_sms_logs', ['member_id'])) {
                $table->dropForeign('financial_sms_logs_member_fk');
            }

            foreach (['bulk_sms_campaign_id', 'member_id', 'sms_segments'] as $column) {
                if (Schema::hasColumn('financial_sms_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
