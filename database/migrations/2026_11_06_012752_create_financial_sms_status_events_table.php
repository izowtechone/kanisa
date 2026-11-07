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
        if (! Schema::hasTable('financial_sms_status_events')) {
            Schema::create('financial_sms_status_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('financial_sms_log_id')->constrained()->cascadeOnDelete();
                $table->string('previous_status', 30)->nullable();
                $table->string('status', 30);
                $table->string('source', 30);
                $table->string('failure_reason', 255)->nullable();
                $table->timestamp('provider_event_at')->nullable();
                $table->timestamps();
            });
        } else {
            $columns = [
                'id', 'financial_sms_log_id', 'previous_status', 'status', 'source',
                'failure_reason', 'provider_event_at', 'created_at', 'updated_at',
            ];
            $foreignKey = collect(Schema::getForeignKeys('financial_sms_status_events'))
                ->contains(fn (array $key): bool => $key['columns'] === ['financial_sms_log_id']
                    && $key['foreign_table'] === 'financial_sms_logs'
                    && $key['foreign_columns'] === ['id']
                    && $key['on_delete'] === 'cascade');

            if (! Schema::hasColumns('financial_sms_status_events', $columns) || ! $foreignKey) {
                throw new RuntimeException('The existing financial_sms_status_events table has an incompatible schema.');
            }
        }

        if (! Schema::hasIndex('financial_sms_status_events', ['financial_sms_log_id', 'created_at'])) {
            Schema::table('financial_sms_status_events', function (Blueprint $table): void {
                $table->index(['financial_sms_log_id', 'created_at'], 'fin_sms_events_log_created_idx');
            });
        }

        if (! Schema::hasIndex('financial_sms_status_events', ['status', 'created_at'])) {
            Schema::table('financial_sms_status_events', function (Blueprint $table): void {
                $table->index(['status', 'created_at'], 'fin_sms_events_status_created_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_sms_status_events');
    }
};
