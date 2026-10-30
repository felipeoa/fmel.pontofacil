<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up(): void
    {
        Schema::create('punch_audits', function (Blueprint $table) 
        {
            $table->id();

            $table->foreignId('punch_id')
                ->constrained('punches')
                ->restrictOnDelete();

            // Usuário autenticado que realizou a operação.
            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('action', 20);
            $table->text('reason');

            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();

            $table->timestamps();

            $table->index(['punch_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('punch_audits');
    }
};
