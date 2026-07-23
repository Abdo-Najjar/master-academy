<?php

namespace App\Filament\Admin\Pages;

use App\Settings\AppSettings;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

class ManageAppSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.admin.pages.manage-app-settings';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('App Settings');
    }

    public function getTitle(): string
    {
        return __('App Settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->can('settings.manage') ?? false);
    }

    public function mount(): void
    {
        $settings = app(AppSettings::class);

        $this->form->fill([
            'app_name' => $settings->app_name,
            'primary_color' => $settings->primary_color,
            'secondary_color' => $settings->secondary_color,
            'enable_absence_alerts' => $settings->enable_absence_alerts,
            'absence_alert_threshold' => $settings->absence_alert_threshold,
            'enable_unpaid_attendance_alerts' => $settings->enable_unpaid_attendance_alerts,
            'unpaid_attendance_alert_threshold' => $settings->unpaid_attendance_alert_threshold,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('')
                    ->schema([
                        TextInput::make('app_name')
                            ->label(__('App Name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        ColorPicker::make('primary_color')
                            ->label(__('Primary Color'))
                            ->required(),

                        ColorPicker::make('secondary_color')
                            ->label(__('Secondary Color'))
                            ->required(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make(__('Reminders & Alerts'))
                    ->description(__('Configure automatic notifications sent to admins based on student attendance.'))
                    ->schema([
                        Toggle::make('enable_absence_alerts')
                            ->label(__('Notify admins when a student is absent repeatedly'))
                            ->inline(false),
                        TextInput::make('absence_alert_threshold')
                            ->label(__('Consecutive absences threshold'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->required()
                            ->helperText(__('Send an alert when a student misses this many consecutive lectures.')),
                        Toggle::make('enable_unpaid_attendance_alerts')
                            ->label(__('Notify admins about unpaid students who keep attending'))
                            ->inline(false),
                        TextInput::make('unpaid_attendance_alert_threshold')
                            ->label(__('Attended lectures threshold with unpaid balance'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->required()
                            ->helperText(__('Send an alert when a student attends this many lectures while their registration has an unpaid balance.')),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = app(AppSettings::class);

        $settings->app_name = $data['app_name'];
        $settings->primary_color = $data['primary_color'];
        $settings->secondary_color = $data['secondary_color'];
        $settings->enable_absence_alerts = (bool) ($data['enable_absence_alerts'] ?? false);
        $settings->absence_alert_threshold = (int) ($data['absence_alert_threshold'] ?? 3);
        $settings->enable_unpaid_attendance_alerts = (bool) ($data['enable_unpaid_attendance_alerts'] ?? false);
        $settings->unpaid_attendance_alert_threshold = (int) ($data['unpaid_attendance_alert_threshold'] ?? 5);
        $settings->save();

        Cache::forget('app_settings_css');

        Notification::make()
            ->success()
            ->title(__('Settings saved successfully'))
            ->send();
    }
}
