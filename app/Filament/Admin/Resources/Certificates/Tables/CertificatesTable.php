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
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

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
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('section_id', null))
                            ->required(),
                        Select::make('template_id')
                            ->label(__('Template'))
                            ->options(CertificateTemplate::where('is_active', true)->get()->pluck('name', 'id'))
                            ->required(),
                        Select::make('section_id')
                            ->label(__('Section'))
                            ->options(function (Get $get): array {
                                $studentId = $get('student_id');
                                if (! $studentId) {
                                    return [];
                                }

                                return Section::query()
                                    ->whereHas('registrations', fn ($q) => $q->where('student_id', $studentId))
                                    ->get()
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->required()
                            ->placeholder(fn (Get $get): string => $get('student_id')
                                ? __('Select a section')
                                : __('Select a student first')),
                    ])
                    ->action(function (array $data, $livewire): void {
                        $student = Student::findOrFail($data['student_id']);
                        $template = CertificateTemplate::findOrFail($data['template_id']);
                        $section = Section::find($data['section_id']);
                        $cert = CertificateService::issue($student, $template, $section);

                        $url = route('admin.pdf.certificate-image', $cert);

                        // Best-effort: open the certificate in a new tab automatically.
                        $livewire->js('window.open('.json_encode($url).", '_blank')");

                        // Reliable fallback button (real click → not popup-blocked).
                        Notification::make()
                            ->success()
                            ->title(__('Certificate issued successfully'))
                            ->actions([
                                Action::make('open')
                                    ->label(__('Open Certificate'))
                                    ->url($url, shouldOpenInNewTab: true)
                                    ->button(),
                            ])
                            ->persistent()
                            ->send();
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
                    Action::make('download_image')
                        ->label(__('Download Image'))
                        ->icon('heroicon-o-photo')
                        ->color('gray')
                        ->url(fn (Certificate $record): string => route('admin.pdf.certificate-image', $record), shouldOpenInNewTab: true),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('id', 'desc');
    }
}
