<?php

namespace App\Filament\Admin\Pages;

use App\Settings\AppSettings;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ManageAppSettings extends Page implements HasForms
{
    use HasHexaLite, InteractsWithForms;

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
        return hexa()->can('settings.manage');
    }

    public function defineGates(): array
    {
        return [
            'settings.manage' => __('Manage App Settings'),
        ];
    }

    public function roleName(): string
    {
        return __('App Settings');
    }

    public function mount(): void
    {
        $settings = app(AppSettings::class);

        $this->form->fill([
            'app_name' => $settings->app_name,
            'logo_path' => $settings->logo_path,
            'primary_color' => $settings->primary_color,
            'secondary_color' => $settings->secondary_color,
            'enable_absence_alerts' => $settings->enable_absence_alerts,
            'absence_alert_threshold' => $settings->absence_alert_threshold,
            'enable_unpaid_attendance_alerts' => $settings->enable_unpaid_attendance_alerts,
            'unpaid_attendance_alert_threshold' => $settings->unpaid_attendance_alert_threshold,
            'sibling_discount_percent' => $settings->sibling_discount_percent,
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

                        FileUpload::make('logo_path')
                            ->label(__('Logo'))
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('settings')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'])
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

                Section::make(__('Discounts'))
                    ->description(__('Auto-applied discounts during enrollment.'))
                    ->schema([
                        TextInput::make('sibling_discount_percent')
                            ->label(__('Sibling Discount (%)'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required()
                            ->suffix('%')
                            ->helperText(__('Auto-applied when the new student\'s parent phone matches another student\'s parent phone. Set to 0 to disable.')),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = app(AppSettings::class);

        // Delete old logo if replaced
        if (
            isset($data['logo_path'])
            && $data['logo_path'] !== $settings->logo_path
            && $settings->logo_path
        ) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        $settings->app_name = $data['app_name'];
        $settings->logo_path = $data['logo_path'] ?? null;
        $settings->primary_color = $data['primary_color'];
        $settings->secondary_color = $data['secondary_color'];
        $settings->enable_absence_alerts = (bool) ($data['enable_absence_alerts'] ?? false);
        $settings->absence_alert_threshold = (int) ($data['absence_alert_threshold'] ?? 3);
        $settings->enable_unpaid_attendance_alerts = (bool) ($data['enable_unpaid_attendance_alerts'] ?? false);
        $settings->unpaid_attendance_alert_threshold = (int) ($data['unpaid_attendance_alert_threshold'] ?? 5);
        $settings->sibling_discount_percent = (int) ($data['sibling_discount_percent'] ?? 0);
        $settings->save();

        Cache::forget('app_settings_css');

        Notification::make()
            ->success()
            ->title(__('Settings saved successfully'))
            ->send();
    }
}
