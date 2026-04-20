<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Ownership
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Which maintenance year this invoice covers (1, 2, or 3)
            $table->unsignedTinyInteger('year');                  // 1 | 2 | 3

            // Amounts
            $table->decimal('amount', 10, 2);                     // annual total  (1500 / 2700 / 3300)
            $table->decimal('monthly_amount', 10, 2);             // per-month     (125  / 225  / 325)

            // When the invoice becomes payable (contract activation date + (year-1) * 12 months)
            $table->date('due_date');

            // User-uploaded receipt
            $table->string('receipt_path')->nullable();

            // Workflow status
            $table->enum('status', [
                'pending',        // waiting for user to upload receipt
                'admin_pending',  // receipt uploaded, waiting for admin
                'approved',       // admin accepted
                'rejected',       // admin rejected — a new invoice will be created
            ])->default('pending');

            // Admin note (reason for rejection etc.)
            $table->text('admin_notes')->nullable();

            // Links a re-issued invoice back to the invoice that was rejected
            $table->foreignId('parent_invoice_id')
                  ->nullable()
                  ->constrained('invoices')
                  ->nullOnDelete();

            $table->timestamps();

            // Indexes for common query patterns
            $table->index(['user_id', 'status']);
            $table->index(['contract_id', 'year']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
