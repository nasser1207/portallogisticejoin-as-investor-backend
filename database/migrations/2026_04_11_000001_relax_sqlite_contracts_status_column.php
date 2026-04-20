<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite only: Laravel enum() creates a CHECK with a fixed value list.
 * If 2026_04_10 ran before SQLite handling was added, this migration relaxes `status` to a string
 * so values like need_to_pay / receipt_review / accepted can be stored.
 */
return new class extends Migration
{
    public function up(): void
    {
      

        Schema::table('contracts', function (Blueprint $table) {
            $table->string('status', 40)->default('draft')->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

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
    }
};
