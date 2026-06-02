<?php

namespace App\Filament\Admin\Pages;

use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Unique;

class EditProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected string $view = 'filament.admin.pages.edit-profile';

    protected static ?int $navigationSort = 999;

    public ?array $profileData = [];

    public ?array $passwordData = [];

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public function mount(): void
    {
        $user = Auth::user();

        $this->profileForm->fill([
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'whatsapp_number' => $user->whatsapp_number,
            'avatar_url' => $user->avatar_url,
        ]);

        $this->passwordForm->fill([]);
    }

    protected function getForms(): array
    {
        return [
            'profileForm',
            'passwordForm',
        ];
    }

    public function profileForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Profile Information'))
                    ->description(__('Update your account profile information.'))
                    ->aside()
                    ->schema([
                        FileUpload::make('avatar_url')
                            ->label(__('Avatar'))
                            ->avatar()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('avatars')
                            ->visibility('public')
                            ->maxSize(1024)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']),

                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->required()
                            ->unique(
                                table: 'users',
                                column: 'email',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'),
                            )
                            ->maxLength(255),

                        TextInput::make('phone_number')
                            ->label(__('Phone'))
                            ->tel()
                            ->maxLength(255),

                        TextInput::make('whatsapp_number')
                            ->label(__('WhatsApp'))
                            ->tel()
                            ->maxLength(255),
                    ]),
            ])
            ->statePath('profileData')
            ->model(Auth::user());
    }

    public function passwordForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Update Password'))
                    ->aside()
                    ->description(__('Ensure your account is using a long, random password to stay secure.'))
                    ->schema([
                        TextInput::make('current_password')
                            ->label(__('Current Password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->currentPassword(),

                        TextInput::make('password')
                            ->label(__('New Password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->same('password_confirmation')
                            ->validationAttribute('new password'),

                        TextInput::make('password_confirmation')
                            ->label(__('Confirm Password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->dehydrated(false),
                    ]),
            ])
            ->statePath('passwordData');
    }

    public function updateProfile(): void
    {
        $data = $this->profileForm->getState();

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (isset($data['avatar_url']) && $data['avatar_url'] !== $user->avatar_url && $user->avatar_url) {
            Storage::disk('public')->delete($user->avatar_url);
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'whatsapp_number' => $data['whatsapp_number'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? $user->avatar_url,
        ]);

        Notification::make()
            ->success()
            ->title(__('Profile updated successfully'))
            ->send();
    }

    public function updatePassword(): void
    {
        $data = $this->passwordForm->getState();

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $user->update([
            'password' => $data['password'],
        ]);

        $this->passwordForm->fill([]);

        Notification::make()
            ->success()
            ->title(__('Password updated successfully'))
            ->send();
    }

    public static function getNavigationLabel(): string
    {
        return __('My Profile');
    }

    public function getTitle(): string
    {
        return __('My Profile');
    }
}
