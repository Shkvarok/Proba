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
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('lesson_id');
            $table->boolean('is_completed')->default(false);
            $table->decimal('progress_percentage', 5, 2)->default(0); // 0-100%
            $table->unsignedInteger('time_spent')->default(0); // секунди
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->json('additional_data')->nullable(); // для зберігання додаткових даних
            $table->timestamps();

            // Індекси та зовнішні ключі
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
            
            // Унікальний індекс - один прогрес на користувача за урок
            $table->unique(['user_id', 'lesson_id']);
            
            // Індекси для швидкого пошуку
            $table->index(['user_id', 'is_completed']);
            $table->index(['lesson_id', 'is_completed']);
            $table->index('last_accessed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};