<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone_number')->nullable()->after('email');
            $table->string('whatsapp_number')->nullable()->after('phone_number');
            $table->string('ssn')->nullable()->after('whatsapp_number');
            $table->string('avatar_url')->nullable()->after('ssn');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['phone_number', 'whatsapp_number', 'ssn', 'avatar_url', 'deleted_at']);
        });
    }
};
