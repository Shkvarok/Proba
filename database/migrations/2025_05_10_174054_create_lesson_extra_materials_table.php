<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_extra_materials', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            
            // Використовуємо той же тип, що і в таблиці lessons
            $table->unsignedInteger('lesson_id');
            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
            
            $table->enum('material_type', ['url', 'video', 'file', 'text'])->default('text');
            $table->text('content')->nullable();
            $table->string('file_path')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_extra_materials');
    }
};