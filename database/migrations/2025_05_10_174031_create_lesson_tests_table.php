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
        // Перевіряємо, чи існує таблиця lesson_tests
        if (!Schema::hasTable('lesson_tests')) {
            // Якщо таблиці немає, створюємо її
            Schema::create('lesson_tests', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('lesson_id');
                $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
                $table->enum('source_type', ['internal', 'external'])->default('external');
                $table->string('external_url')->nullable();
                $table->integer('time_limit_minutes')->nullable();
                $table->integer('passing_score')->default(70);
                $table->timestamps();
            });
        } else {
            // Якщо таблиця існує, оновлюємо її
            Schema::table('lesson_tests', function (Blueprint $table) {
                // Перевіряємо, чи існує колонка source_type
                if (!Schema::hasColumn('lesson_tests', 'source_type')) {
                    $table->enum('source_type', ['internal', 'external'])->default('external');
                } else {
                    // Змінюємо source_type щоб підтримувати internal тести
                    $table->enum('source_type', ['internal', 'external'])->default('external')->change();
                }
                
                // Робимо external_url необов'язковим, оскільки для internal тестів він не потрібен
                if (Schema::hasColumn('lesson_tests', 'external_url')) {
                    $table->string('external_url')->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('lesson_tests')) {
            Schema::table('lesson_tests', function (Blueprint $table) {
                if (Schema::hasColumn('lesson_tests', 'source_type')) {
                    // Повертаємо до попереднього стану (якщо це було)
                    $table->dropColumn('source_type');
                }
            });
        }
    }
};