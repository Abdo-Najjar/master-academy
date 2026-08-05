<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * Builds an Arabic/English tabbed input for a spatie-translatable attribute.
 *
 * The form state for a translatable attribute is its raw translations array
 * (e.g. ['ar' => '...', 'en' => '...']), so dot-notation fields like
 * "name.ar" / "name.en" read and write each locale directly.
 */
class TranslatableInput
{
    public static function make(string $field, string $label, bool $required = true): Tabs
    {
        return Tabs::make(__('Translations'))
            ->columnSpanFull()
            ->tabs([
                Tab::make(__('Arabic'))->schema([
                    TextInput::make($field.'.ar')
                        ->label($label)
                        ->required($required)
                        ->maxLength(255),
                ]),
                Tab::make(__('English'))->schema([
                    TextInput::make($field.'.en')
                        ->label($label)
                        ->maxLength(255),
                ]),
            ]);
    }

    /** Same as make(), for longer copy such as descriptions and quotes. */
    public static function textarea(string $field, string $label, bool $required = true, int $rows = 3): Tabs
    {
        return Tabs::make(__('Translations'))
            ->columnSpanFull()
            ->tabs([
                Tab::make(__('Arabic'))->schema([
                    Textarea::make($field.'.ar')
                        ->label($label)
                        ->required($required)
                        ->rows($rows)
                        ->maxLength(2000),
                ]),
                Tab::make(__('English'))->schema([
                    Textarea::make($field.'.en')
                        ->label($label)
                        ->rows($rows)
                        ->maxLength(2000),
                ]),
            ]);
    }
}
