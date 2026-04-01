<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sadq_nafath_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->string('national_id', 32);
            $table->string('contract_type', 32)->nullable();
            $table->string('status', 32)->default('pending');
            $table->json('last_payload')->nullable();
            $table->timestamps();

            $table->index(['national_id', 'contract_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sadq_nafath_requests');
    }
};
