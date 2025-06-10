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
        Schema::create('test_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_test_id')->constrained()->onDelete('cascade');
            $table->text('question_text');
            $table->enum('question_type', ['single_choice', 'multiple_choice', 'text_input']);
            $table->integer('position')->default(0); // Порядок питання
            $table->integer('points')->default(1); // Бали за правильну відповідь
            $table->text('explanation')->nullable(); // Пояснення до питання
            $table->boolean('is_required')->default(true); // Чи обов'язкове питання
            
            // Медіафайли для питання
            $table->enum('media_type', ['image', 'video'])->nullable(); // Тип медіафайлу
            $table->string('media_path')->nullable(); // Шлях до файлу
            $table->string('media_original_name')->nullable(); // Оригінальна назва файлу
            $table->bigInteger('media_size')->nullable(); // Розмір файлу в байтах
            $table->string('media_mime_type')->nullable(); // MIME тип файлу
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_questions');
    }
};