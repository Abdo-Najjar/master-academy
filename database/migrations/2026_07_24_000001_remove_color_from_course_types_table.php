<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_types', function (Blueprint $table): void {
            $table->dropColumn('color');
        });
    }

    public function down(): void
    {
        Schema::table('course_types', function (Blueprint $table): void {
            $table->string('color', 16)->nullable();
        });
    }
};
