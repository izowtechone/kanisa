<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return array<string, callable(Blueprint): mixed>
     */
    private function columns(): array
    {
        return [
            'provider' => fn (Blueprint $table) => $table->string('provider', 30)->nullable(),
            'idempotency_key' => fn (Blueprint $table) => $table->string('idempotency_key', 64)->nullable(),
            'provider_batch_key' => fn (Blueprint $table) => $table->string('provider_batch_key', 64)->nullable(),
            'provider_campaign_uid' => fn (Blueprint $table) => $table->string('provider_campaign_uid', 100)->nullable(),
            'provider_status' => fn (Blueprint $table) => $table->string('provider_status', 30)->nullable(),
            'provider_error_code' => fn (Blueprint $table) => $table->string('provider_error_code', 60)->nullable(),
            'provider_acceptance_state' => fn (Blueprint $table) => $table->string('provider_acceptance_state', 40)->nullable(),
            'provider_units' => fn (Blueprint $table) => $table->unsignedSmallInteger('provider_units')->nullable(),
            'provider_failure_reason' => fn (Blueprint $table) => $table->string('provider_failure_reason', 255)->nullable(),
            'provider_event_at' => fn (Blueprint $table) => $table->timestamp('provider_event_at')->nullable(),
            'delivered_at' => fn (Blueprint $table) => $table->timestamp('delivered_at')->nullable(),
        ];
    }

    public function up(): void
    {
        Schema::table('financial_sms_logs', function (Blueprint $table): void {
            foreach ($this->columns() as $column => $definition) {
                if (! Schema::hasColumn('financial_sms_logs', $column)) {
                    $definition($table);
                }
            }
        });

        if (! Schema::hasIndex('financial_sms_logs', ['provider_message_id'])) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->index('provider_message_id', 'financial_sms_logs_provider_message_idx');
            });
        }

        if (! Schema::hasIndex('financial_sms_logs', ['provider_batch_key'])) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->index('provider_batch_key', 'financial_sms_logs_batch_key_idx');
            });
        }

        DB::table('financial_sms_logs')
            ->whereNull('provider')
            ->whereNotNull('provider_message_id')
            ->update(['provider' => 'beem']);
    }

    public function down(): void
    {
        Schema::table('financial_sms_logs', function (Blueprint $table): void {
            $table->dropIndex('financial_sms_logs_provider_message_idx');
            $table->dropIndex('financial_sms_logs_batch_key_idx');
            $table->dropColumn(array_keys($this->columns()));
        });
    }
};
