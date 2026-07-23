<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('unique_id')->unique();
            $table->string('status')->default('initializing')->index();
            $table->string('phone_number')->nullable();
            $table->string('name')->nullable();
            $table->string('profile_picture_path')->nullable();
            $table->longText('qr_code')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};
