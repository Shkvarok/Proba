<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
        
            $table->unsignedInteger('role_id');       
            $table->unsignedInteger('permission_id');
        
            $table->timestamps();
        
            $table->unique(['role_id', 'permission_id'], 'unique_role_permission');
        
            $table->foreign('role_id')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('cascade');
        
            $table->foreign('permission_id')
                  ->references('id')
                  ->on('permissions')
                  ->onDelete('cascade');
        });
        
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};

