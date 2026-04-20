<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add investment amount columns to contracts
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('total_amount', 15, 2)->nullable()->after('approved_at');
            $table->decimal('monthly_payment_amount', 15, 2)->nullable()->after('total_amount');
        });

        // 2. Create payments table
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->unsignedTinyInteger('month_number');       // 1–12
            $table->decimal('amount', 15, 2);
            $table->date('due_date');
            $table->date('payment_date')->nullable();
            $table->enum('status', [
                'pending',
                'sent',
                'received',
                'reported_missing',
            ])->default('pending');
            $table->timestamps();

            // One row per month per contract
            $table->unique(['contract_id', 'month_number']);
            // Fast queries by contract + status
            $table->index(['contract_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['total_amount', 'monthly_payment_amount']);
        });
    }
};
