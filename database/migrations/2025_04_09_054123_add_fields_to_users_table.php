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
            // Додаємо нові поля до таблиці users
            $table->unsignedInteger('country_id')->nullable()->after('id');
            $table->string('phone_number', 20)->nullable()->after('email');
            $table->string('first_name', 50)->nullable()->after('phone_number');
            $table->string('last_name', 50)->nullable()->after('first_name');
            $table->string('avatar', 255)->nullable()->after('last_name');
            $table->unsignedInteger('role_id')->nullable()->after('avatar');
            $table->softDeletes(); // Додаємо поле deleted_at для soft deletes
            
            // Змінюємо поле name на nullable, оскільки тепер використовуватимемо first_name та last_name
            $table->string('name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Видаляємо додані поля
            $table->dropColumn([
                'country_id',
                'phone_number',
                'first_name',
                'last_name',
                'avatar',
                'role_id',
                'deleted_at'
            ]);
            
            // Повертаємо поле name як not nullable
            $table->string('name')->nullable(false)->change();
        });
    }
};