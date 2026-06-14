<?php

namespace App\Filament\Admin\Resources\Certificates\Tables;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Section;
use App\Models\Student;
use App\Services\CertificateService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('serial_number')->label(__('Serial'))->searchable()->sortable(),
                TextColumn::make('student.name')->label(__('Student'))->searchable(),
                TextColumn::make('section.name')->label(__('Section'))->searchable()->placeholder('—'),
                TextColumn::make('section.subject.name')->label(__('Subject'))->placeholder('—'),
                TextColumn::make('template.name')->label(__('Template')),
                TextColumn::make('issued_at')->label(__('Issued'))->dateTime()->sortable(),
                TextColumn::make('issuedBy.name')->label(__('Issued By'))->placeholder('—'),
            ])
            ->headerActions([
                Action::make('issue_certificate')
                    ->label(__('Issue Certificate'))
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->schema([
                        Select::make('student_id')
                            ->label(__('Student'))
                            ->options(Student::query()->orderBy('id')->get()->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('template_id')
                            ->label(__('Template'))
                            ->options(CertificateTemplate::where('is_active', true)->pluck('name', 'id'))
                            ->required(),
                        Select::make('section_id')
                            ->label(__('Section (optional)'))
                            ->options(Section::query()->get()->pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->action(function (array $data): void {
                        $student = Student::findOrFail($data['student_id']);
                        $template = CertificateTemplate::findOrFail($data['template_id']);
                        $section = isset($data['section_id']) ? Section::find($data['section_id']) : null;
                        CertificateService::issue($student, $template, $section);
                    }),
            ])
            ->filters([
                SelectFilter::make('template_id')
                    ->label(__('Template'))
                    ->relationship('template', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('download_pdf')
                        ->label(__('Download PDF'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->action(function (Certificate $record): StreamedResponse {
                            $pdfContent = CertificateService::generatePdf($record);

                            return response()->streamDownload(
                                fn () => print($pdfContent),
                                'certificate-'.$record->serial_number.'.pdf',
                                ['Content-Type' => 'application/pdf']
                            );
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('id', 'desc');
    }
}
