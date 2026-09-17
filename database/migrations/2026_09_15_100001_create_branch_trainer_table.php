<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which sites a trainer works at.
     *
     * A trainer is not a resident of one branch the way a room or an employee
     * is — the same person teaches two mornings at one site and three evenings
     * at another, which is why this is a pivot and not a `branch_id` column on
     * `trainers`. The section still decides where a given lesson happens; this
     * records where the trainer is available to be put.
     */
    public function up(): void
    {
        Schema::create('branch_trainer', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'trainer_id']);
        });

        $this->adoptTrainersIntoBranchesTheyAlreadyTeachIn();
    }

    /**
     * Existing trainers already answer this question — their sections say which
     * sites they teach at. Reading it off the sections beats leaving every
     * trainer on record blank and asking the desk to re-enter what the data
     * already knows.
     */
    private function adoptTrainersIntoBranchesTheyAlreadyTeachIn(): void
    {
        $pairs = DB::table('sections')
            ->whereNull('deleted_at')
            ->whereNotNull('trainer_id')
            ->whereNotNull('branch_id')
            ->select('branch_id', 'trainer_id')
            ->distinct()
            ->get();

        if ($pairs->isEmpty()) {
            return;
        }

        $now = now();

        DB::table('branch_trainer')->insertOrIgnore(
            $pairs->map(fn (object $pair): array => [
                'branch_id' => $pair->branch_id,
                'trainer_id' => $pair->trainer_id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_trainer');
    }
};
