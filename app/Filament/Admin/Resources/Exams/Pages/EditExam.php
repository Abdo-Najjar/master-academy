<?php

namespace App\Filament\Admin\Resources\Exams\Pages;

use App\Filament\Admin\Resources\Exams\ExamResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (auth()->user()?->can('exam.update') ?? false);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => (auth()->user()?->can('exam.delete') ?? false)),
        ];
    }
}
