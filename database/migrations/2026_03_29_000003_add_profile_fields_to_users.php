<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->string('father_name')->nullable()->after('last_name');
            $table->string('grandfather_name')->nullable()->after('father_name');
            $table->string('birth_date')->nullable()->after('grandfather_name');
            $table->string('iban')->nullable()->after('birth_date');
            $table->string('bank_name')->nullable()->after('iban');
            $table->string('region')->nullable()->after('bank_name');
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['father_name','grandfather_name','birth_date','iban','bank_name','region']);
        });
    }
};
