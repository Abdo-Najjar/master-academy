<?php

namespace App\Filament\Admin\Pages;

use App\Models\LoginActivity;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LoginActivities extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected string $view = 'filament.admin.pages.login-activities';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('Reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('Login Activities');
    }

    public function getTitle(): string
    {
        return __('Login Activities');
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->can('login_activity.index') ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(LoginActivity::query()->latest('logged_in_at'))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('auth_type')
                    ->label(__('User Type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match (class_basename($state)) {
                        'User' => __('Administrator'),
                        'Student' => __('Student'),
                        'Trainer' => __('Trainer'),
                        default => __(class_basename($state)),
                    })
                    ->color(fn (string $state): string => match (class_basename($state)) {
                        'User' => 'danger',
                        'Student' => 'info',
                        'Trainer' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('auth.name')
                    ->label(__('Name'))
                    ->searchable(),
                TextColumn::make('ip')->label(__('IP'))->searchable(),
                TextColumn::make('browser')->label(__('Browser'))->sortable(),
                TextColumn::make('platform')->label(__('Platform'))->toggleable(),
                TextColumn::make('device')->label(__('Device'))->toggleable(),
                TextColumn::make('logged_in_at')
                    ->label(__('When'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('auth_type')
                    ->label(__('User Type'))
                    ->options([
                        \App\Models\User::class => __('Administrator'),
                        \App\Models\Student::class => __('Student'),
                        \App\Models\Trainer::class => __('Trainer'),
                    ]),
            ])
            ->defaultSort('logged_in_at', 'desc');
    }
}
