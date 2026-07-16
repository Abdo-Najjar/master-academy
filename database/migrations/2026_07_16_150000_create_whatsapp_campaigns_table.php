<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('message');
            $table->foreignId('student_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft'); // draft, running, completed, cancelled
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone');
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaign_recipients');
        Schema::dropIfExists('whatsapp_campaigns');
    }
};
