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
        Schema::create('lessons', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->unsignedInteger('course_id');
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->enum('type', ['lecture', 'video', 'test']); // Типи уроків
            $table->unsignedInteger('order')->default(0); // Порядок уроку в курсі
            $table->string('file_path', 255)->nullable(); // Шлях до файлу (PDF)
            $table->string('video_url', 255)->nullable(); // Посилання на відео (YouTube або локальне)
            $table->string('test_url', 255)->nullable(); // Посилання на Google Forms
            $table->boolean('is_free')->default(false); // Позначити як безкоштовний урок для перегляду
            $table->integer('duration_minutes')->nullable(); // Тривалість уроку в хвилинах
            $table->boolean('is_published')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};