<?php

namespace App\Filament\Admin\Resources\Exams\Pages;

use App\Filament\Admin\Resources\Exams\ExamResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExams extends ListRecords
{
    use ExportsTableRecords;

    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
            CreateAction::make()->visible(fn () => (auth()->user()?->can('exam.create') ?? false)),
        ];
    }
}
