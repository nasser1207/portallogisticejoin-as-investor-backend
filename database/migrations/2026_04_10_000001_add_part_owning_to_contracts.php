<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('total_amount_paid', 10, 2)->default(0)->after('total_amount');
            $table->timestamp('timer_started_at')->nullable()->after('total_amount_paid');
            $table->string('sale_receipt_path')->nullable()->after('timer_started_at');
        });

        $this->extendStatusEnum();
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['total_amount_paid', 'timer_started_at', 'sale_receipt_path']);
        });

        $this->revertStatusEnum();
    }

    /**
     * MySQL/MariaDB: extend ENUM with sale workflow values.
     * SQLite: Laravel's enum() adds a CHECK listing only original values — relax to VARCHAR so new statuses persist.
     */
    private function extendStatusEnum(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('contracts', function (Blueprint $table) {
                $table->string('status', 40)->default('draft')->change();
            });

            return;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $values = implode(',', array_map(
            fn (string $v) => "'".str_replace("'", "\\'", $v)."'",
            [
                'draft',
                'sent',
                'nafath_pending',
                'nafath_approved',
                'admin_pending',
                'approved',
                'rejected',
                'cancelled',
                'need_to_pay',
                'receipt_review',
                'accepted',
            ]
        ));

        DB::statement("ALTER TABLE `contracts` MODIFY `status` ENUM({$values}) NOT NULL DEFAULT 'draft'");
    }

    /**
     * MySQL/MariaDB only. Fails if any row still uses need_to_pay, receipt_review, or accepted.
     */
    private function revertStatusEnum(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('contracts', function (Blueprint $table) {
                $table->enum('status', [
                    'draft',
                    'sent',
                    'nafath_pending',
                    'nafath_approved',
                    'admin_pending',
                    'approved',
                    'rejected',
                    'cancelled',
                ])->default('draft')->change();
            });

            return;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $values = implode(',', array_map(
            fn (string $v) => "'".str_replace("'", "\\'", $v)."'",
            [
                'draft',
                'sent',
                'nafath_pending',
                'nafath_approved',
                'admin_pending',
                'approved',
                'rejected',
                'cancelled',
            ]
        ));

        DB::statement("ALTER TABLE `contracts` MODIFY `status` ENUM({$values}) NOT NULL DEFAULT 'draft'");
    }
};
