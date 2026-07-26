<?php

namespace App\Filament\Admin\Resources\StudentGroups\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Group Details'))
                    ->description(__('The group name and the students who belong to it.'))
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('students')
                            ->label(__('Students'))
                            ->relationship('students', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Additional Contacts'))
                    ->description(__('People who are not registered students but should still be reachable through this group.'))
                    ->icon('heroicon-o-phone')
                    ->afterHeader([
                        Actions::make([
                            Action::make('downloadContactsTemplate')
                                ->label(__('Download Template'))
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('gray')
                                ->action(fn (): StreamedResponse => self::downloadContactsTemplate()),
                            Action::make('importContacts')
                                ->label(__('Import from Excel'))
                                ->icon('heroicon-o-arrow-up-tray')
                                ->color('gray')
                                ->modalHeading(__('Import Contacts'))
                                ->modalDescription(__('An Excel file with two columns: Name, Phone. A header row is optional.'))
                                ->schema([
                                    FileUpload::make('file')
                                        ->label(__('Excel File'))
                                        ->required()
                                        ->storeFiles(false)
                                        ->acceptedFileTypes([
                                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                        ]),
                                ])
                                ->action(function (array $data, callable $get, callable $set): void {
                                    $file = $data['file'] ?? null;

                                    if (! $file instanceof TemporaryUploadedFile) {
                                        return;
                                    }

                                    $imported = self::parseContactsFile($file);
                                    $existing = $get('contacts') ?? [];
                                    $set('contacts', [...$existing, ...$imported]);

                                    Notification::make()
                                        ->success()
                                        ->title(__(':count contact(s) imported', ['count' => count($imported)]))
                                        ->send();
                                }),
                        ]),
                    ])
                    ->schema([
                        Repeater::make('contacts')
                            ->hiddenLabel()
                            ->relationship('contacts')
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('phone')
                                    ->label(__('Phone'))
                                    ->tel()
                                    ->required()
                                    ->maxLength(50),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->collapsible()
                            ->addActionLabel(__('Add Contact'))
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    protected static function downloadContactsTemplate(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $writer = new Writer();
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([__('Name'), __('Phone')]));
            $writer->addRow(Row::fromValues(['خالد أبو شنب', '0599123456']));

            $writer->close();
        }, __('Contacts Template').'.xlsx');
    }

    /** @return array<int, array{name: string, phone: string}> */
    protected static function parseContactsFile(TemporaryUploadedFile $file): array
    {
        $reader = new Reader();
        $reader->open($file->getRealPath());

        $rows = [];
        $isFirstRow = true;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->toArray();
                $name = trim((string) ($cells[0] ?? ''));
                $phone = trim((string) ($cells[1] ?? ''));

                if ($isFirstRow) {
                    $isFirstRow = false;

                    // Skip what looks like a header row (phone column isn't mostly digits).
                    if (strlen(preg_replace('/\D+/', '', $phone)) < 7) {
                        continue;
                    }
                }

                if ($name === '' || $phone === '') {
                    continue;
                }

                $rows[] = ['name' => $name, 'phone' => $phone];
            }

            break;
        }

        $reader->close();

        return $rows;
    }
}
