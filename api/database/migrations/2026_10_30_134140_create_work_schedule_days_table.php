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
        Schema::create('work_schedule_days', function (Blueprint $table) {
            $table->id();

            $table->foreignId('work_schedule_id')
                ->constrained()
                ->cascadeOnDelete();

            // 1 = segunda-feira, 7 = domingo
            $table->unsignedTinyInteger('weekday');

            $table->boolean('is_working_day')->default(true);

            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->unsignedSmallInteger('expected_minutes')->default(0);

            $table->timestamps();

            $table->unique(['work_schedule_id', 'weekday']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedule_days');
    }
};
