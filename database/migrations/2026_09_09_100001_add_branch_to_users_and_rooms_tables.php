<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Branches stop being a label on a section and become the wall between one
     * site and the next: an employee belongs to a branch, a room stands in one,
     * and neither sees past it.
     *
     * The columns stay nullable on purpose. An employee with no branch is head
     * office and sees the whole centre, and a centre that has not sorted its
     * rooms out yet must not have half its data vanish on deploy — the forms
     * are what require a branch from here on.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $this->adoptOrphansIntoTheOnlyBranch();
    }

    /**
     * A centre running on a single branch has nothing to decide: everything
     * already belongs to it, and leaving the rows unassigned would hide them
     * from every employee of that branch. With two or more branches there is
     * nothing safe to guess, so the rows are left for someone to assign by hand
     * rather than filed under the wrong site.
     */
    private function adoptOrphansIntoTheOnlyBranch(): void
    {
        $branches = DB::table('branches')->whereNull('deleted_at')->pluck('id');

        if ($branches->count() !== 1) {
            return;
        }

        $branchId = (int) $branches->first();

        foreach (['rooms', 'sections', 'expenses', 'room_bookings'] as $table) {
            if (Schema::hasColumn($table, 'branch_id')) {
                DB::table($table)->whereNull('branch_id')->update(['branch_id' => $branchId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
