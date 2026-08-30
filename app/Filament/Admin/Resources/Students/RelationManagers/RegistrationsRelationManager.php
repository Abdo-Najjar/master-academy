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

    /**
     * Without these the create button and its modal fall back to the raw class
     * name — "إضافة Registration" — because a relation manager derives its
     * labels from the related model, not from RegistrationResource.
     */
    protected static function getModelLabel(): ?string
    {
        return __('Registration');
    }

    protected static function getPluralModelLabel(): ?string
    {
        return __('Registrations');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, ViewRecord::class)
            || is_subclass_of($pageClass, EditRecord::class);
    }

    /**
     * Enrolling is a normal thing to do while looking at a student, so the
     * panel-wide "relation managers are read-only on view pages" default is
     * lifted here — otherwise the only way to add a course is to open the edit
     * screen first.
     */
    public function isReadOnly(): bool
    {
        return false;
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
