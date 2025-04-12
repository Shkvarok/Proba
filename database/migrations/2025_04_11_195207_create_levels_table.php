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
        Schema::create('levels', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->string('code', 20)->unique();
            $table->string('name', 50)->unique();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        // Наповнення таблиці рівнів
        DB::table('levels')->insert([
            ['code' => 'beginner', 'name' => 'Початковий', 'description' => 'Для новачків без попереднього досвіду'],
            ['code' => 'intermediate', 'name' => 'Середній', 'description' => 'Для тих, хто має базові знання'],
            ['code' => 'advanced', 'name' => 'Просунутий', 'description' => 'Для досвідчених користувачів'],
            ['code' => 'all-levels', 'name' => 'Всі рівні', 'description' => 'Підходить для будь-якого рівня знань'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('levels');
    }
};