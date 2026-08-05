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
        Schema::table('trainers', function (Blueprint $table): void {
            $table->json('specialty')->nullable()->after('bio');
            $table->json('student_opinion')->nullable()->after('specialty');
            $table->boolean('show_on_site')->default(false)->after('student_opinion');
            $table->integer('site_sort_order')->default(0)->after('show_on_site');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainers', function (Blueprint $table): void {
            $table->dropColumn(['specialty', 'student_opinion', 'show_on_site', 'site_sort_order']);
        });
    }
};
