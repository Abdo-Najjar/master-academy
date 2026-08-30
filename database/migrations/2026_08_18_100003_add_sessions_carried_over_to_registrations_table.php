<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sessions the student had already been charged for somewhere else.
     *
     * Moving a student to another section carries their progress through the
     * current cycle with them — they do not start paying from zero because the
     * centre reshuffled the groups. The counter is now recomputed from the
     * lessons of the section the registration points at, so the sessions
     * counted in the previous section have to be written down or the transfer
     * would quietly hand them back.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->unsignedInteger('sessions_carried_over')->default(0)->after('session_offset');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropColumn('sessions_carried_over');
        });
    }
};
