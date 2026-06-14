<?php

namespace App\Filament\Admin\Pages;

use App\Models\WhatsappSession;
use App\Services\WhatsappLinkService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Hexters\HexaLite\HasHexaLite;
use Livewire\Attributes\On;

class WhatsAppSettings extends Page
{
    use HasHexaLite;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected string $view = 'filament.admin.pages.whatsapp-settings';

    protected static ?int $navigationSort = 99;

    public ?WhatsappSession $session = null;

    public string $testPhone = '';

    public string $testMessage = '';

    public static function getNavigationGroup(): ?string
    {
        return __('Administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('WhatsApp');
    }

    public function getTitle(): string
    {
        return __('WhatsApp Settings');
    }

    public static function canAccess(): bool
    {
        return hexa()->can('whatsapp.manage');
    }

    public function defineGates(): array
    {
        return [
            'whatsapp.manage' => __('Manage WhatsApp'),
        ];
    }

    public function mount(): void
    {
        $this->session = WhatsappSession::linked()->latest()->first()
            ?? WhatsappSession::whereNull('deleted_at')->latest()->first();
    }

    public function startLink(): void
    {
        $this->session = WhatsappLinkService::startLink();

        Notification::make()
            ->info()
            ->title(__('Linking started — scan the QR code below'))
            ->send();
    }

    public function pollStatus(): void
    {
        if (! $this->session || $this->session->status === WhatsappSession::STATUS_READY) {
            return;
        }

        $this->session = WhatsappLinkService::syncStatus($this->session);

        if ($this->session->status === WhatsappSession::STATUS_READY) {
            Notification::make()
                ->success()
                ->title(__('WhatsApp connected successfully!'))
                ->body($this->session->phone_number)
                ->send();
        }
    }

    public function logout(): void
    {
        if (! $this->session) {
            return;
        }

        WhatsappLinkService::logout($this->session);
        $this->session = null;

        Notification::make()
            ->warning()
            ->title(__('WhatsApp account disconnected'))
            ->send();
    }

    public function sendTest(): void
    {
        $this->validate([
            'testPhone'   => ['required', 'string'],
            'testMessage' => ['required', 'string', 'max:1000'],
        ]);

        $ok = \App\Services\WhatsAppService::send($this->testPhone, $this->testMessage);

        if ($ok) {
            Notification::make()->success()->title(__('Message sent successfully'))->send();
            $this->testPhone = '';
            $this->testMessage = '';
        } else {
            Notification::make()->danger()->title(__('Failed to send message'))->body(__('Check that WhatsApp is still connected and try again.'))->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('startLink')
                ->label(__('Link WhatsApp Account'))
                ->icon('heroicon-o-link')
                ->color('success')
                ->visible(fn () => ! $this->session || in_array($this->session->status, [
                    WhatsappSession::STATUS_DISCONNECTED,
                    WhatsappSession::STATUS_ERROR,
                ]))
                ->action('startLink'),

            Action::make('logout')
                ->label(__('Disconnect'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->session && $this->session->status === WhatsappSession::STATUS_READY)
                ->requiresConfirmation()
                ->modalHeading(__('Disconnect WhatsApp?'))
                ->modalDescription(__('This will unlink the WhatsApp account. Notifications will stop until re-linked.'))
                ->action('logout'),
        ];
    }
}
