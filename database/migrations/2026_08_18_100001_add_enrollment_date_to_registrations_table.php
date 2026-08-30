<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The day the student actually joined the section, as opposed to the day
     * the row happened to be typed into the system.
     *
     * Per-session billing used to start counting from however many sessions
     * the section had held at the moment the registration was inserted
     * (`session_offset`), which only holds up when the data is entered in
     * chronological order. A centre entering three months of history in one
     * sitting enters every student first and every lesson afterwards, so every
     * backdated lesson was charged to every student — including the ones who
     * only joined last week. Counting against a real date fixes that whatever
     * order the rows arrive in.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->date('enrolled_at')->nullable()->after('section_id');
            $table->index(['section_id', 'enrolled_at']);
        });

        // Existing rows were created in chronological order, so the day the row
        // was inserted is the best available answer for when the student joined.
        DB::table('registrations')
            ->whereNull('enrolled_at')
            ->update(['enrolled_at' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropIndex(['section_id', 'enrolled_at']);
            $table->dropColumn('enrolled_at');
        });
    }
};
