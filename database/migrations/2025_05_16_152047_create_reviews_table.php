<?php
// 2025_05_16_100000_create_reviews_table.php

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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('course_id');
            $table->text('content');
            $table->tinyInteger('rating')->unsigned()->default(5); // Рейтинг від 1 до 5
            $table->boolean('is_approved')->default(false); // Для модерації
            $table->timestamps();
            $table->softDeletes(); // Для м'якого видалення

            // Індекси та зовнішні ключі
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            
            // Унікальний індекс, щоб користувач міг залишити тільки один відгук для курсу
            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('review_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('review_id');
            $table->unsignedBigInteger('user_id');
            $table->text('content');
            $table->unsignedBigInteger('parent_id')->nullable(); // Для ієрархії коментарів
            $table->boolean('is_approved')->default(false); // Для модерації
            $table->timestamps();
            $table->softDeletes(); // Для м'якого видалення

            // Індекси та зовнішні ключі
            $table->foreign('review_id')->references('id')->on('reviews')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('review_comments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_comments');
        Schema::dropIfExists('reviews');
    }
};