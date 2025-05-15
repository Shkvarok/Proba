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
        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id(); // Створює bigint
            $table->unsignedBigInteger('user_id'); // bigint для відповідності з users.id
            $table->unsignedInteger('course_id'); // integer для відповідності з courses.id
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->enum('enrollment_type', ['purchase', 'subscription', 'free', 'gift']);
            $table->unsignedBigInteger('payment_id')->nullable(); // bigint для відповідності з payments.id
            $table->boolean('is_active')->default(true);
            $table->unique(['user_id', 'course_id']);
            $table->timestamps();
            
            // Додаємо зовнішні ключі
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_enrollments');
    }
};