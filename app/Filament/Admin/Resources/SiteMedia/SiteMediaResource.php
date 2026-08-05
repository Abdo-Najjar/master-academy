<?php

namespace App\Filament\Admin\Resources\SiteMedia;

use App\Filament\Admin\Resources\SiteMedia\Pages\ManageSiteMedia;
use App\Filament\Support\AuthorizesResourceActions;
use App\Filament\Support\TranslatableInput;
use App\Models\SiteMedia;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiteMediaResource extends Resource
{
    use AuthorizesResourceActions;

    protected static ?string $model = SiteMedia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationGroup(): ?string
    {
        return __('Website');
    }

    public static function getModelLabel(): string
    {
        return __('Gallery Item');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Gallery');
    }

    public static function permissionPrefix(): string
    {
        return 'site_media';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TranslatableInput::make('title', __('Title')),
                        Select::make('type')
                            ->label(__('Type'))
                            ->options(SiteMedia::typeOptions())
                            ->default('image')
                            ->required()
                            ->live(),
                        SpatieMediaLibraryFileUpload::make('file')
                            ->label(__('File'))
                            ->collection('file')
                            ->acceptedFileTypes(fn (callable $get): array => $get('type') === 'video'
                                ? ['video/mp4', 'video/webm', 'video/quicktime']
                                : ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                            ->maxSize(51200)
                            ->columnSpanFull(),
                        TextInput::make('url')
                            ->label(__('External URL'))
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText(__('Used only when no file is uploaded — handy for videos hosted elsewhere.')),
                        TextInput::make('sort_order')
                            ->label(__('Sort Order'))
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Toggle::make('is_active')
                            ->label(__('Show on site'))
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('file')
                    ->label(__('File'))
                    ->collection('file'),
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SiteMedia::typeOptions()[$state] ?? $state),
                TextColumn::make('url')
                    ->label(__('External URL'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')
                    ->label(__('Show on site')),
                TextColumn::make('sort_order')
                    ->label(__('Sort Order'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(SiteMedia::typeOptions()),
                TernaryFilter::make('is_active')->label(__('Show on site')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->visible(static::canUpdateRecords()),
                    DeleteAction::make()
                        ->visible(static::canDeleteRecords()),
                    ForceDeleteAction::make()
                        ->visible(static::canDeleteRecords()),
                    RestoreAction::make()
                        ->visible(static::canDeleteRecords()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(static::canDeleteRecords()),
                    ForceDeleteBulkAction::make()
                        ->visible(static::canDeleteRecords()),
                    RestoreBulkAction::make()
                        ->visible(static::canDeleteRecords()),
                ]),
            ])
            ->reorderable('sort_order', fn (): bool => static::allows('update'))
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSiteMedia::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
