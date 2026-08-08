<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campaigns could only target a saved student group. They can now target a
     * section, a subject, a trainer, or every active student.
     */
    public function up(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table): void {
            // group | section | subject | trainer | all
            $table->string('target_type')->default('group')->after('student_group_id');
            $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['target_type', 'target_id']);
        });
    }
};
