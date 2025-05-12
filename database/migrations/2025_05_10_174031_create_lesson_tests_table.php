<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_tests', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            
            // Використовуємо той же тип, що і в таблиці lessons
            $table->unsignedInteger('lesson_id');
            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
            
            $table->enum('source_type', ['url', 'internal'])->default('url');
            $table->text('external_url')->nullable();
            $table->integer('time_limit_minutes')->nullable();
            $table->integer('passing_score')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_tests');
    }
};