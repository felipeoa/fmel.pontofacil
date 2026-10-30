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
        Schema::create('daily_work_summaries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('work_schedule_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->date('work_date');

            $table->unsignedSmallInteger('expected_minutes')->default(0);
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->unsignedSmallInteger('break_minutes')->default(0);

            $table->integer('balance_minutes')->default(0);

            $table->string('status', 20)->default('pending');

            $table->timestamp('calculated_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_work_summaries');
    }
};
