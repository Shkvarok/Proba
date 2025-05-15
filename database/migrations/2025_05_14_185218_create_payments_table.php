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
        Schema::create('payments', function (Blueprint $table) {
            $table->id(); // Створює bigint як у users
            $table->unsignedBigInteger('user_id'); // bigint для відповідності з users.id
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('UAH');
            $table->string('payment_method', 50);
            $table->enum('payment_status', ['pending', 'completed', 'failed', 'refunded']);
            $table->string('transaction_id', 255)->nullable();
            $table->string('entity_type', 50);
            // Якщо entity_id може посилатися на різні таблиці, використовуємо bigint
            // щоб забезпечити сумісність з усіма типами ID
            $table->unsignedBigInteger('entity_id');
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('original_amount', 10, 2)->nullable();
            // Якщо promo_code_id посилається на таблицю з integer, використовуйте unsignedInteger
            $table->unsignedBigInteger('promo_code_id')->nullable();
            $table->timestamps();
            
            // Додаємо зовнішній ключ
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};