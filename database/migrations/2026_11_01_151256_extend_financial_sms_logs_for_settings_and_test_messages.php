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
        if (Schema::hasForeignKey('financial_sms_logs', ['financial_transaction_id'])) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->dropForeign(['financial_transaction_id']);
            });
        }

        if (Schema::hasIndex('financial_sms_logs', 'financial_sms_logs_financial_transaction_id_unique')) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->dropUnique('financial_sms_logs_financial_transaction_id_unique');
            });
        }

        Schema::table('financial_sms_logs', function (Blueprint $table): void {
            $table->foreignId('financial_transaction_id')->nullable()->change();

            if (! Schema::hasColumn('financial_sms_logs', 'notification_type')) {
                $table->string('notification_type', 40)->default('financial_receipt')->after('financial_transaction_id');
            }

            if (! Schema::hasColumn('financial_sms_logs', 'message')) {
                $table->text('message')->nullable();
            }

            if (! Schema::hasColumn('financial_sms_logs', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('is_test');
            }

            if (! Schema::hasColumn('financial_sms_logs', 'delivery_status')) {
                $table->string('delivery_status', 20)->nullable();
            }

            if (! Schema::hasColumn('financial_sms_logs', 'delivery_checked_at')) {
                $table->timestamp('delivery_checked_at')->nullable();
            }
        });

        if (! Schema::hasForeignKey('financial_sms_logs', ['created_by'])) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasIndex(
            'financial_sms_logs',
            ['financial_transaction_id', 'notification_type'],
            'unique',
        )) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->unique(
                    ['financial_transaction_id', 'notification_type'],
                    'financial_sms_logs_transaction_notification_unique',
                );
            });
        }

        if (! Schema::hasIndex('financial_sms_logs', ['notification_type', 'created_at'])) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->index(['notification_type', 'created_at']);
            });
        }

        if (! Schema::hasForeignKey('financial_sms_logs', ['financial_transaction_id'])) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->foreign('financial_transaction_id')
                    ->references('id')
                    ->on('financial_transactions')
                    ->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('financial_sms_logs')->whereNull('financial_transaction_id')->exists()) {
            throw new RuntimeException('Cannot restore the required transaction link while standalone SMS logs exist.');
        }

        if (Schema::hasForeignKey('financial_sms_logs', ['financial_transaction_id'])) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->dropForeign(['financial_transaction_id']);
            });
        }

        if (Schema::hasIndex(
            'financial_sms_logs',
            ['financial_transaction_id', 'notification_type'],
            'unique',
        )) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->dropUnique('financial_sms_logs_transaction_notification_unique');
            });
        }

        if (Schema::hasIndex('financial_sms_logs', ['notification_type', 'created_at'])) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->dropIndex(['notification_type', 'created_at']);
            });
        }

        if (Schema::hasForeignKey('financial_sms_logs', ['created_by'])) {
            Schema::table('financial_sms_logs', function (Blueprint $table): void {
                $table->dropForeign(['created_by']);
            });
        }

        Schema::table('financial_sms_logs', function (Blueprint $table): void {
            $table->dropColumn([
                'notification_type',
                'message',
                'created_by',
                'delivery_status',
                'delivery_checked_at',
            ]);
            $table->foreignId('financial_transaction_id')->nullable(false)->change();
            $table->unique('financial_transaction_id');
        });

        Schema::table('financial_sms_logs', function (Blueprint $table): void {
            $table->foreign('financial_transaction_id')
                ->references('id')
                ->on('financial_transactions')
                ->restrictOnDelete();
        });
    }
};
