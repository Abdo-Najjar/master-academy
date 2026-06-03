<?php

namespace App\Filament\Admin\Resources\Exams\Pages;

use App\Filament\Admin\Resources\Exams\ExamResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public static function canAccess(array $parameters = []): bool
    {
        return hexa()->can('exam.create');
    }
}
