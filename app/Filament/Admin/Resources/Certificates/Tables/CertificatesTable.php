<?php

namespace App\Filament\Admin\Resources\Certificates\Tables;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Section;
use App\Models\Student;
use App\Services\CertificateService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
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
use Illuminate\Database\Eloquent\Collection;

class CertificatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('serial_number')->label(__('Serial'))->searchable()->sortable(),
                TextColumn::make('student.name')->label(__('Student'))->searchable(),
                TextColumn::make('section.name')->label(__('Section'))->searchable()->placeholder('—'),
                TextColumn::make('section.subject.name')->label(__('Course'))->placeholder('—'),
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
                Action::make('issue_certificates_section')
                    ->label(__('Issue for Section'))
                    ->icon('heroicon-o-user-group')
                    ->color('info')
                    ->schema([
                        Select::make('section_id')
                            ->label(__('Section'))
                            ->options(Section::query()->orderBy('id', 'desc')->get()->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('template_id')
                            ->label(__('Template'))
                            ->options(CertificateTemplate::where('is_active', true)->get()->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (array $data, $livewire): void {
                        $section = Section::findOrFail($data['section_id']);
                        $template = CertificateTemplate::findOrFail($data['template_id']);

                        $result = CertificateService::issueForSection($section, $template);
                        $issued = $result['issued'];

                        if ($issued->isEmpty()) {
                            Notification::make()
                                ->warning()
                                ->title(__('No certificates issued'))
                                ->body(__('All enrolled students already have a certificate for this section and template.'))
                                ->send();

                            return;
                        }

                        $url = route('admin.pdf.certificate-images-bulk', ['ids' => $issued->pluck('id')->implode(',')]);

                        $livewire->js('window.open('.json_encode($url).", '_blank')");

                        $skipped = $result['skipped'];
                        $body = $skipped > 0
                            ? __(':count certificates issued, :skipped already existed and were skipped.', ['count' => $issued->count(), 'skipped' => $skipped])
                            : __(':count certificates issued.', ['count' => $issued->count()]);

                        Notification::make()
                            ->success()
                            ->title(__('Certificates issued successfully'))
                            ->body($body)
                            ->actions([
                                Action::make('open')
                                    ->label(__('Open Certificates'))
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
                BulkActionGroup::make([
                    BulkAction::make('download_images_bulk')
                        ->label(__('Download Images'))
                        ->icon('heroicon-o-photo')
                        ->color('gray')
                        ->action(function (Collection $records, $livewire): void {
                            $url = route('admin.pdf.certificate-images-bulk', ['ids' => $records->pluck('id')->implode(',')]);
                            $livewire->js('window.open('.json_encode($url).", '_blank')");
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
