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
        Schema::table('lesson_tests', function (Blueprint $table) {
            // Змінюємо source_type щоб підтримувати internal тести
            $table->enum('source_type', ['internal', 'external'])->default('external')->change();
            
            // Робимо external_url необов'язковим, оскільки для internal тестів він не потрібен
            $table->string('external_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_tests', function (Blueprint $table) {
            $table->enum('source_type', ['url', 'internal'])->change();
            $table->string('external_url')->nullable(false)->change();
        });
    }
};