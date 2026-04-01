<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::drop('contracts');

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['sale', 'rental']);
            $table->string('title');
            $table->string('file_path')->nullable();
            $table->string('nafath_reference')->nullable();
            $table->enum('status', [
                'draft',
                'sent',
                'nafath_pending',
                'nafath_approved',
                'admin_pending',
                'approved',
                'rejected',
                'cancelled',
            ])->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
