<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            
            // Використовуємо той же тип, що і в таблиці modules
            $table->unsignedInteger('module_id');
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
            
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['lecture', 'test', 'extra_material']);
            $table->integer('position');
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};