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
        Schema::table('lesson_lectures', function (Blueprint $table) {
            // Змінюємо enum щоб підтримувати 'mixed' тип
            $table->enum('content_type', ['text', 'file', 'mixed'])->default('text')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_lectures', function (Blueprint $table) {
            // Повертаємо до попереднього стану
            $table->enum('content_type', ['text', 'file'])->default('text')->change();
        });
    }
};
