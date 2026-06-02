<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->json('name');
            $table->date('dob')->nullable();
            $table->string('ssn')->nullable();
            $table->string('username')->nullable();
            $table->string('student_number')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->string('phone_number')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('parent_name')->nullable();
            $table->string('parent_phone')->nullable();
            $table->string('parent_whatsapp')->nullable();
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['ssn', 'deleted_at']);
            $table->unique(['username', 'deleted_at']);
            $table->unique(['student_number', 'deleted_at']);
            $table->unique(['email', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
