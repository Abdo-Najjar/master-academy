<?php

namespace App\Filament\Admin\Resources\Students\Pages;

use App\Filament\Admin\Resources\Students\Actions\WalletActions;
use App\Filament\Admin\Resources\Students\StudentResource;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('studentCard')
                ->label(__('Student Card'))
                ->icon('heroicon-o-identification')
                ->color('gray')
                ->url(fn (Student $record): string => route('admin.pdf.student-card', $record), shouldOpenInNewTab: true),
            Action::make('notifyParent')
                ->label(__('Notify Parent (WhatsApp)'))
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('success')
                ->visible(fn (Student $record): bool => filled($record->parent_whatsapp) || filled($record->parent_phone))
                ->url(function (Student $record): string {
                    $phone = preg_replace('/[^0-9]/', '', (string) ($record->parent_whatsapp ?: $record->parent_phone));
                    $msg = urlencode(__(':app — about your child :name', [
                        'app' => \App\Support\AppBranding::appName(),
                        'name' => is_array($record->name) ? ($record->name[app()->getLocale()] ?? reset($record->name)) : $record->name,
                    ]));
                    return "https://wa.me/{$phone}?text={$msg}";
                }, shouldOpenInNewTab: true),
            WalletActions::deposit(),
            WalletActions::withdraw(),
            EditAction::make(),
        ];
    }
}
