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
            $table->string('last_name', 50)->nullable()->after('name'); // Змінено з 'after('first_name')'
            $table->string('avatar', 255)->nullable()->after('last_name');
            $table->unsignedInteger('role_id')->nullable()->after('avatar');
            $table->softDeletes(); // Додаємо поле deleted_at для soft deletes
            
            // Оскільки ви вирішили використовувати name як обов'язкове поле,
            // цей рядок можна видалити, щоб name залишалося not nullable
            // $table->string('name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
   public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        // Перевіряємо існування колонок перед видаленням
        if (Schema::hasColumn('users', 'country_id')) {
            $table->dropColumn('country_id');
        }
        if (Schema::hasColumn('users', 'phone_number')) {
            $table->dropColumn('phone_number');
        }
        if (Schema::hasColumn('users', 'last_name')) {
            $table->dropColumn('last_name');
        }
        if (Schema::hasColumn('users', 'avatar')) {
            $table->dropColumn('avatar');
        }
        if (Schema::hasColumn('users', 'role_id')) {
            $table->dropColumn('role_id');
        }
        if (Schema::hasColumn('users', 'deleted_at')) {
            $table->dropColumn('deleted_at');
        }
    });
}
};