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
        Schema::create('statuses', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->string('code', 30);  // Видалено ->unique()
            $table->string('name', 50);
            $table->string('type', 30);
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Додано комбіновану унікальність
            $table->unique(['code', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};