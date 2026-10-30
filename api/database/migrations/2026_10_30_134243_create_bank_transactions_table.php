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
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('daily_work_summary_id')
                ->nullable()
                ->constrained('daily_work_summaries')
                ->nullOnDelete();

            $table->uuid('uuid')->unique();

            // credit, debit, adjustment
            $table->string('transaction_type', 20);

            // Positivo = crédito; negativo = débito
            $table->integer('minutes');

            $table->string('description', 255);

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
