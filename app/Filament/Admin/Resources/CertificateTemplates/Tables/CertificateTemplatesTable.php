<?php

namespace App\Filament\Admin\Resources\CertificateTemplates\Tables;

use App\Filament\Admin\Resources\CertificateTemplates\CertificateTemplateResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CertificateTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('background')
                    ->label(__('Preview'))
                    ->collection('background')
                    ->width(80)
                    ->height(55),
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('canvas_width')->label(__('Width'))->suffix('px'),
                TextColumn::make('canvas_height')->label(__('Height'))->suffix('px'),
                TextColumn::make('certificates_count')->counts('certificates')->label(__('Issued')),
                IconColumn::make('is_active')->label(__('Active'))->boolean(),
                TextColumn::make('created_at')->label(__('Created'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('design')
                        ->label(__('Design'))
                        ->icon('heroicon-o-paint-brush')
                        ->color('success')
                        ->url(fn ($record) => CertificateTemplateResource::getUrl('design', ['record' => $record])),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
