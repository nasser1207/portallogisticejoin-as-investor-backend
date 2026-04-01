<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->unique()->after('email');
            }
            if (!Schema::hasColumn('users', 'national_id')) {
                $table->string('national_id')->nullable()->unique()->after('phone');
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default('user')->after('password'); // admin, user
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status', 20)->default('active')->after('role'); // active, inactive
            }
            if (!Schema::hasColumn('users', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('status');
            }
            if (!Schema::hasColumn('users', 'is_first_login')) {
                $table->boolean('is_first_login')->default(true)->after('is_verified');
            }
            if (!Schema::hasColumn('users', 'otp_code')) {
                $table->string('otp_code', 10)->nullable()->after('is_first_login');
            }
            if (!Schema::hasColumn('users', 'otp_expiry')) {
                $table->timestamp('otp_expiry')->nullable()->after('otp_code');
            }
            if (!Schema::hasColumn('users', 'api_token')) {
                $table->string('api_token', 80)->nullable()->unique()->after('remember_token');
            }
        });

        // Make email nullable only on MySQL.
        if (Schema::hasColumn('users', 'email') && DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = ['first_name', 'last_name', 'phone', 'national_id', 'role', 'status', 'is_verified', 'is_first_login', 'otp_code', 'otp_expiry', 'api_token'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
