<?php

namespace App\Filament\Admin\Resources\Testimonials;

use App\Filament\Admin\Resources\Testimonials\Pages\ManageTestimonials;
use App\Filament\Support\AuthorizesResourceActions;
use App\Filament\Support\TranslatableInput;
use App\Models\Testimonial;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TestimonialResource extends Resource
{
    use AuthorizesResourceActions;

    protected static ?string $model = Testimonial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Website');
    }

    public static function getModelLabel(): string
    {
        return __('Testimonial');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Testimonials');
    }

    public static function permissionPrefix(): string
    {
        return 'testimonial';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TranslatableInput::make('name', __('Name')),
                        TranslatableInput::make('role', __('Role'), required: false),
                        TranslatableInput::textarea('quote', __('Quote'), rows: 4),
                        Select::make('student_id')
                            ->label(__('Linked Student'))
                            ->relationship('student', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText(__('Optional. When set, the student photo is used if no avatar is uploaded.')),
                        SpatieMediaLibraryFileUpload::make('avatar')
                            ->label(__('Photo'))
                            ->collection('avatar')
                            ->image()
                            ->imageEditor()
                            ->avatar(),
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
                SpatieMediaLibraryImageColumn::make('avatar')
                    ->label(__('Photo'))
                    ->collection('avatar')
                    ->circular(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label(__('Role'))
                    ->searchable(),
                TextColumn::make('quote')
                    ->label(__('Quote'))
                    ->limit(60)
                    ->wrap(),
                ToggleColumn::make('is_active')
                    ->label(__('Show on site')),
                TextColumn::make('sort_order')
                    ->label(__('Sort Order'))
                    ->sortable(),
            ])
            ->filters([
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
            'index' => ManageTestimonials::route('/'),
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
