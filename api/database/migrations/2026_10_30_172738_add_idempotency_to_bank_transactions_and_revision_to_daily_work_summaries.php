<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->string('source_key', 120)
                ->nullable()
                ->unique();
        });

        Schema::table('daily_work_summaries', function (Blueprint $table) {
            $table->unsignedInteger('revision')
                ->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('daily_work_summaries', function (Blueprint $table) {
            $table->dropColumn('revision');
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropUnique(['source_key']);
            $table->dropColumn('source_key');
        });
    }
};
