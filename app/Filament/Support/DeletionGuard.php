<?php

namespace App\Filament\Support;

use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DeletionGuard
{
    /**
     * Abort the current delete action if the record has any of the given relations.
     *
     * @param  array<string, string>  $relations  relation method name => human-readable label
     */
    public static function ensureUnused(Model $record, array $relations): void
    {
        $blockers = [];

        foreach ($relations as $relation => $label) {
            if ($record->{$relation}()->exists()) {
                $blockers[] = $label;
            }
        }

        if ($blockers === []) {
            return;
        }

        static::block($blockers);
    }

    /**
     * Abort the current bulk delete action if any of the records has any of the given relations.
     *
     * @param  Collection<int, Model>  $records
     * @param  array<string, string>  $relations
     */
    public static function ensureUnusedForMany(Collection $records, array $relations): void
    {
        $blockers = [];

        foreach ($records as $record) {
            foreach ($relations as $relation => $label) {
                if ($record->{$relation}()->exists()) {
                    $blockers[$label] = $label;
                }
            }
        }

        if ($blockers === []) {
            return;
        }

        static::block(array_values($blockers));
    }

    /**
     * @param  list<string>  $blockers
     */
    protected static function block(array $blockers): void
    {
        Notification::make()
            ->danger()
            ->title(__('Cannot Delete'))
            ->body(__('This record is linked to :items and cannot be deleted. Remove those links first.', [
                'items' => implode('، ', $blockers),
            ]))
            ->send();

        throw new Halt;
    }
}
