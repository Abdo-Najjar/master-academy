<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A real "lesson" entity. Until now a session only existed implicitly as a
     * distinct attendance date, which made it impossible to record a cancelled
     * lesson, a paid makeup lesson, or a private lesson with its own fee and
     * trainer share — and impossible to bill per number of sessions held.
     */
    public function up(): void
    {
        Schema::create('section_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // regular | makeup | private
            $table->string('type')->default('regular');
            // scheduled | held | cancelled
            $table->string('status')->default('held');

            $table->string('cancellation_reason')->nullable();

            // Private lessons carry their own fee and trainer percentage,
            // independent of the section's.
            $table->decimal('fee', 10, 2)->nullable();
            $table->decimal('trainer_rate', 5, 2)->nullable();

            // Regular + makeup lessons advance the student's billing counter;
            // private lessons never do.
            $table->boolean('counts_toward_billing')->default(true);
            $table->boolean('counted_at_billing')->default(false);

            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['section_id', 'date']);
            $table->index(['section_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_sessions');
    }
};
