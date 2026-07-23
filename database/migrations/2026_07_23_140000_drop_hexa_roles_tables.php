<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('hexa_role_user');
        Schema::dropIfExists('hexa_roles');
    }

    public function down(): void
    {
        Schema::create('hexa_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('name');
            $table->foreignId('team_id')->nullable();
            $table->string('created_by_name')->nullable();
            $table->json('access')->nullable();
            $table->json('gates')->nullable();
            $table->json('checkall')->nullable();
            $table->string('guard')->default('web');
            $table->timestamps();
        });

        Schema::create('hexa_role_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('hexa_roles')->cascadeOnDelete();
            $table->foreignId('user_id');
        });
    }
};
