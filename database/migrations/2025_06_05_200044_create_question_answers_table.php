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
        Schema::create('question_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_question_id')->constrained()->onDelete('cascade');
            $table->text('answer_text');
            $table->boolean('is_correct')->default(false);
            $table->integer('position')->default(0); // Порядок відповіді
            
            // Медіафайли для відповіді
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
        Schema::dropIfExists('question_answers');
    }
};