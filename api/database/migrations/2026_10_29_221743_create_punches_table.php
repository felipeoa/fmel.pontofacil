
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('punches', function (Blueprint $table)
        {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->uuid('uuid')->unique();
            $table->string('type', 20);
            $table->dateTime('punched_at');
            $table->dateTime('received_at');

            $table->string('source', 20)->default('manual');
            $table->boolean('synced_offline')->default(false);

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy_meters', 8, 2)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'punched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('punches');
    }
};
