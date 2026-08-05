<?php

namespace App\Filament\Admin\Pages;

use App\Settings\SiteSettings;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected string $view = 'filament.admin.pages.manage-site-settings';

    protected static ?int $navigationSort = 5;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Site Settings');
    }

    public function getTitle(): string
    {
        return __('Site Settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Website');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('site_settings.manage') ?? false;
    }

    public function mount(): void
    {
        $settings = app(SiteSettings::class);

        $this->form->fill([
            'hero_eyebrow' => $settings->hero_eyebrow,
            'hero_title' => $settings->hero_title,
            'hero_title_highlight' => $settings->hero_title_highlight,
            'hero_lead' => $settings->hero_lead,
            'hero_badge_title' => $settings->hero_badge_title,
            'hero_badge_note' => $settings->hero_badge_note,
            'stats' => $settings->stats,
            'about_text' => $settings->about_text,
            'about_values' => array_map(fn (string $value): array => ['value' => $value], $settings->about_values),
            'director_name' => $settings->director_name,
            'director_role' => $settings->director_role,
            'director_quote' => $settings->director_quote,
            'contact_phone' => $settings->contact_phone,
            'contact_whatsapp' => $settings->contact_whatsapp,
            'license_number' => $settings->license_number,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Hero Section'))
                    ->schema([
                        TextInput::make('hero_eyebrow')
                            ->label(__('Eyebrow'))
                            ->required()
                            ->maxLength(120),
                        TextInput::make('hero_title')
                            ->label(__('Title (first line)'))
                            ->required()
                            ->maxLength(120),
                        TextInput::make('hero_title_highlight')
                            ->label(__('Title (highlighted line)'))
                            ->required()
                            ->maxLength(120),
                        TextInput::make('hero_lead')
                            ->label(__('Lead paragraph'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('hero_badge_title')
                            ->label(__('Accreditation badge title'))
                            ->required()
                            ->maxLength(120),
                        TextInput::make('hero_badge_note')
                            ->label(__('Accreditation badge note'))
                            ->required()
                            ->maxLength(160),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('Impact Numbers'))
                    ->schema([
                        Repeater::make('stats')
                            ->label('')
                            ->schema([
                                TextInput::make('value')
                                    ->label(__('Number'))
                                    ->required()
                                    ->maxLength(20),
                                TextInput::make('label')
                                    ->label(__('Description'))
                                    ->required()
                                    ->maxLength(160),
                            ])
                            ->columns(2)
                            ->maxItems(3)
                            ->defaultItems(3)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make(__('About Us'))
                    ->schema([
                        Textarea::make('about_text')
                            ->label(__('About text'))
                            ->required()
                            ->rows(4)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Repeater::make('about_values')
                            ->label(__('Selling points'))
                            ->simple(
                                TextInput::make('value')
                                    ->label(__('Selling point'))
                                    ->required()
                                    ->maxLength(60)
                            )
                            ->maxItems(6)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make(__('Director’s Message'))
                    ->schema([
                        TextInput::make('director_name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(120),
                        TextInput::make('director_role')
                            ->label(__('Role'))
                            ->required()
                            ->maxLength(120),
                        Textarea::make('director_quote')
                            ->label(__('Quote'))
                            ->required()
                            ->rows(5)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('Contact Us'))
                    ->schema([
                        TextInput::make('contact_phone')
                            ->label(__('Phone number'))
                            ->tel()
                            ->required()
                            ->maxLength(30),
                        TextInput::make('contact_whatsapp')
                            ->label(__('WhatsApp number'))
                            ->required()
                            ->maxLength(30)
                            ->helperText(__('Digits only, including the country code — used to build the wa.me link.')),
                        TextInput::make('license_number')
                            ->label(__('License number'))
                            ->required()
                            ->maxLength(30),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = app(SiteSettings::class);

        $settings->hero_eyebrow = $data['hero_eyebrow'];
        $settings->hero_title = $data['hero_title'];
        $settings->hero_title_highlight = $data['hero_title_highlight'];
        $settings->hero_lead = $data['hero_lead'];
        $settings->hero_badge_title = $data['hero_badge_title'];
        $settings->hero_badge_note = $data['hero_badge_note'];
        $settings->stats = array_values(array_map(
            fn (array $stat): array => ['value' => $stat['value'], 'label' => $stat['label']],
            $data['stats'] ?? []
        ));
        $settings->about_text = $data['about_text'];
        $settings->about_values = array_values($data['about_values'] ?? []);
        $settings->director_name = $data['director_name'];
        $settings->director_role = $data['director_role'];
        $settings->director_quote = $data['director_quote'];
        $settings->contact_phone = $data['contact_phone'];
        $settings->contact_whatsapp = $data['contact_whatsapp'];
        $settings->license_number = $data['license_number'];
        $settings->save();

        Notification::make()
            ->success()
            ->title(__('Settings saved successfully'))
            ->send();
    }
}
