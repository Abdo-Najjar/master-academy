<?php

namespace App\Filament\Support;

use App\Support\TrainerRate;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * The trainer's cut, entered either as a fraction or as a percentage.
 *
 * Only the percentage is stored. The fraction picker is a shortcut that writes
 * into it — one source of truth, so nothing downstream has to know which way
 * the number was entered. Editing an existing record shows the fraction back
 * when the stored percentage is one.
 */
class TrainerRateField
{
    /**
     * @param  string  $name  the percentage column ('trainer_rate', 'default_rate')
     * @param  bool  $nullable  whether an emptied box may be stored as null. On
     *                          `sections.trainer_rate` it must be — empty means
     *                          "inherit the trainer's default". On the NOT NULL
     *                          `trainers.default_rate` it may not: an empty box
     *                          there means no cut, so it is written as a zero.
     * @return array<int, Field>
     */
    public static function make(
        string $name = 'trainer_rate',
        ?string $label = null,
        ?string $helperText = null,
        bool $required = false,
        ?\Closure $visible = null,
        float|int|null $default = null,
        bool $nullable = true,
    ): array {
        $modeField = $name.'_fraction';

        $fields = [
            Select::make($modeField)
                ->label(__('Rate as a fraction'))
                ->options(TrainerRate::options())
                ->placeholder(__('Custom percentage'))
                ->native(false)
                ->live()
                ->dehydrated(false)
                // Not a column: seeded from whatever percentage is stored so an
                // existing "third" comes back as a third, not as 33.3333.
                ->afterStateHydrated(function (Select $component, Get $get) use ($name): void {
                    $component->state(TrainerRate::match($get($name)));
                })
                ->afterStateUpdated(function ($state, Set $set) use ($name): void {
                    if ($percent = TrainerRate::percent((string) $state)) {
                        $set($name, $percent);
                    }
                })
                ->helperText(__('Pick a fraction to fill the percentage, or leave it empty and type one.')),

            TextInput::make($name)
                ->label($label ?? __('Trainer Rate (%)'))
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                // Four places, so a third is 33.3333 rather than 33.33 — the
                // difference is a whole piaster on a 300 ₪ fee.
                ->step(0.0001)
                ->suffix('%')
                ->default($default)
                ->required($required)
                ->dehydrateStateUsing(fn ($state) => $nullable || ! blank($state) ? $state : 0)
                ->live(onBlur: true)
                // Typing a percentage by hand re-syncs the picker, so the two
                // never disagree about what the rate is.
                ->afterStateUpdated(function ($state, Set $set) use ($modeField): void {
                    $set($modeField, TrainerRate::match($state));
                })
                ->helperText($helperText),
        ];

        if ($visible) {
            // Both halves share one condition — showing the picker without the
            // percentage it fills would be nonsense.
            $fields = array_map(fn ($field) => $field->visible($visible), $fields);
        }

        return $fields;
    }
}
