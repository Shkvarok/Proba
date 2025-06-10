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
        Schema::create('internal_tests', function (Blueprint $table) {
            $table->id();
            // Використовуємо unsignedInteger щоб відповідати типу в таблиці lessons
            $table->unsignedInteger('lesson_id');
            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
            
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('passing_score')->default(70); // Відсоток для проходження тесту
            $table->integer('time_limit_minutes')->nullable(); // Ліміт часу в хвилинах
            $table->enum('status', ['active', 'draft'])->default('draft');
            $table->boolean('randomize_questions')->default(false); // Чи показувати питання в рандомному порядку
            $table->integer('questions_to_show')->nullable(); // Скільки питань показати (null = всі)
            $table->integer('max_attempts')->default(3); // Максимальна кількість спроб
            $table->boolean('show_results_immediately')->default(true); // Показувати результати одразу
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_tests');
    }
};