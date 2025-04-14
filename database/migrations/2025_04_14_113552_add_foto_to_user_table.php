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
        Schema::table('users', function (Blueprint $table) {
            // Додаємо поле для збереження шляху до профільного фото користувача
            // Розміщуємо його після поля avatar
            $table->string('profile_photo', 255)->nullable()->after('avatar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Видаляємо поле при відкаті міграції
            $table->dropColumn('profile_photo');
        });
    }
};