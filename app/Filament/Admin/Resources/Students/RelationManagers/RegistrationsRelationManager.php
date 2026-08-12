<?php

namespace App\Filament\Admin\Resources\Students\RelationManagers;

use App\Filament\Admin\Resources\Registrations\Schemas\RegistrationForm;
use App\Filament\Admin\Resources\Registrations\Tables\RegistrationsTable;
use App\Filament\Support\EnrollmentPayment;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RegistrationsRelationManager extends RelationManager
{
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Registrations');
    }

    protected static string $relationship = 'registrations';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, ViewRecord::class)
            || is_subclass_of($pageClass, EditRecord::class);
    }

    public function form(Schema $schema): Schema
    {
        return RegistrationForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return RegistrationsTable::configure($table)
            ->recordTitleAttribute('id')
            ->emptyStateHeading(__('No records found'))
            ->headerActions([
                CreateAction::make()
                    // The payment has to reach the wallet before the charge
                    // does, so it is banked here rather than after creation.
                    ->mutateDataUsing(fn (array $data): array => EnrollmentPayment::collect(
                        $data,
                        $this->getOwnerRecord()->getKey(),
                    )),
            ]);
    }
}
