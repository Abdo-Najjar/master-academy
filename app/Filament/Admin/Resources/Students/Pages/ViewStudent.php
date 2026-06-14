<?php

namespace App\Filament\Admin\Resources\Students\Pages;

use App\Filament\Admin\Resources\Students\Actions\TransferSectionAction;
use App\Filament\Admin\Resources\Students\Actions\WalletActions;
use App\Filament\Admin\Resources\Students\StudentResource;
use App\Models\CertificateTemplate;
use App\Models\Section;
use App\Models\Student;
use App\Services\CertificateService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            Action::make('issueCertificate')
                ->label(__('Issue Certificate'))
                ->icon('heroicon-o-academic-cap')
                ->color('info')
                ->schema([
                    Select::make('template_id')
                        ->label(__('Template'))
                        ->options(CertificateTemplate::where('is_active', true)->get()->pluck('name', 'id'))
                        ->required(),
                    Select::make('section_id')
                        ->label(__('Section'))
                        ->options(fn (Student $record) => $record->registrations()
                            ->with('section')
                            ->get()
                            ->pluck('section.name', 'section.id')
                            ->filter()
                        )
                        ->searchable()
                        ->required(),
                ])
                ->action(function (Student $record, array $data): StreamedResponse {
                    $template = CertificateTemplate::findOrFail($data['template_id']);
                    $section = Section::find($data['section_id']);
                    $cert = CertificateService::issue($record, $template, $section);
                    $pdf = CertificateService::generatePdf($cert);

                    return response()->streamDownload(
                        fn () => print($pdf),
                        'certificate-'.$cert->serial_number.'.pdf',
                        ['Content-Type' => 'application/pdf']
                    );
                }),
            TransferSectionAction::make(),
            WalletActions::deposit(),
            WalletActions::withdraw(),
            EditAction::make(),
        ];
    }
}
