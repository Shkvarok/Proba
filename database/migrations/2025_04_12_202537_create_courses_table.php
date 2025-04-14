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
        Schema::create('courses', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->unsignedInteger('category_id');
            
            // Використовуємо unsignedBigInteger для instructor_id,
            // щоб відповідати типу id у таблиці users
            $table->unsignedBigInteger('instructor_id')->nullable();
            
            $table->decimal('price', 10, 2)->default(0.00);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->timestamp('discount_expires_at')->nullable();
            $table->unsignedInteger('level_id');
            $table->string('language', 50)->default('українська');
            $table->string('cover_image', 255)->nullable();
            $table->string('promo_video_url', 255)->nullable();
            $table->text('requirements')->nullable();
            $table->text('what_you_learn')->nullable();
            $table->boolean('is_published')->default(false);
            $table->string('meta_title', 255)->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('restrict');
            $table->foreign('instructor_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('level_id')->references('id')->on('levels')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};