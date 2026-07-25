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
use OpenSpout\Reader\XLSX\Reader;

class StudentGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
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
                        Actions::make([
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
                        Repeater::make('contacts')
                            ->label(__('Additional Contacts'))
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
                            ->addActionLabel(__('Add Contact'))
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ]),
            ]);
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
