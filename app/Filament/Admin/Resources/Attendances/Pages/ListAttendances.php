<?php

namespace App\Filament\Admin\Resources\Attendances\Pages;

use App\Filament\Admin\Pages\TakeAttendance;
use App\Filament\Admin\Resources\Attendances\AttendanceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('takeAttendance')
                ->label(__('Take Attendance'))
                ->icon('heroicon-o-check-badge')
                ->color('primary')
                ->url(fn (): string => TakeAttendance::getUrl()),
        ];
    }
}
