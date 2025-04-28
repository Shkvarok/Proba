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
        Schema::create('student_courses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Студент
            $table->unsignedInteger('course_id'); // Курс
            $table->timestamp('access_granted_at')->useCurrent(); // Коли надано доступ
            $table->timestamp('expires_at')->nullable(); // Термін дії (якщо є)
            $table->boolean('is_active')->default(true); // Чи активний доступ
            $table->unsignedBigInteger('granted_by')->nullable(); // Хто надав доступ
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('granted_by')->references('id')->on('users')->onDelete('set null');
            
            // Забезпечення унікальності доступу
            $table->unique(['user_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_courses');
    }
};