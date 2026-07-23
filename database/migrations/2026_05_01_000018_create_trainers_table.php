<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainers', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->json('name');
            $table->date('dob')->nullable();
            $table->string('ssn')->nullable();
            $table->string('username')->nullable();
            $table->string('trainer_number')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->string('phone_number')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('default_rate', 5, 2)->default(0);
            $table->text('bio')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['ssn', 'deleted_at']);
            $table->unique(['username', 'deleted_at']);
            $table->unique(['trainer_number', 'deleted_at']);
            $table->unique(['email', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainers');
    }
};
