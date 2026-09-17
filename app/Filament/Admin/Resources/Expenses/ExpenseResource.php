<?php

namespace App\Filament\Admin\Resources\Expenses;

use App\Filament\Admin\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Support\AuthorizesResourceActions;
use App\Filament\Support\BranchField;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\PaymentType;
use App\Support\ReceiptAttachment;
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
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Money going out, recorded the way money coming in already is: which kind of
 * expense it was, how much, how it was paid, and the receipt for it.
 */
class ExpenseResource extends Resource
{
    use AuthorizesResourceActions;

    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingDown;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('Finance');
    }

    public static function getModelLabel(): string
    {
        return __('Expense');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Expenses');
    }

    public static function permissionPrefix(): string
    {
        return 'expense';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        Select::make('expense_type_id')
                            ->label(__('Expense Type'))
                            ->options(fn (): array => ExpenseType::all()->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(),
                        TextInput::make('amount')
                            ->label(__('Amount'))
                            ->numeric()
                            ->prefix('₪')
                            ->required()
                            ->minValue(0.01)
                            ->step(0.01),
                        DatePicker::make('spent_at')
                            ->label(__('Expense Date'))
                            ->native(false)
                            ->default(now())
                            ->required(),
                        // Same vocabulary the desk uses when it takes money from
                        // a student, so "how was it paid" reads the same in both
                        // directions.
                        Select::make('payment_type_id')
                            ->label(__('Payment Type'))
                            ->options(fn (): array => PaymentType::all()->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->native(false),
                        BranchField::make(),
                        TextInput::make('payee')
                            ->label(__('Paid To'))
                            ->maxLength(255),
                        TextInput::make('reference')
                            ->label(__('Receipt Number'))
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('')
                    ->schema([
                        ReceiptAttachment::field(),
                        Textarea::make('note')
                            ->label(__('Note'))
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['expenseType', 'paymentType', 'branch']))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('spent_at')->label(__('Expense Date'))->date()->sortable(),
                TextColumn::make('expenseType.name')->label(__('Expense Type'))->searchable()->sortable(),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->money('ILS')
                    ->sortable()
                    // A finance screen is read for its total as often as for its
                    // rows, and the total has to follow the filters.
                    ->summarize(Sum::make()->label(__('Total Expenses'))->money('ILS')),
                TextColumn::make('paymentType.name')->label(__('Payment Type'))->placeholder('—')->toggleable(),
                TextColumn::make('branch.name')->label(__('Branch'))->placeholder('—')->toggleable(),
                TextColumn::make('payee')->label(__('Paid To'))->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('reference')->label(__('Receipt Number'))->searchable()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('receipt')
                    ->label(__('Receipt'))
                    ->state(fn (Expense $record): ?string => $record->receiptUrl() ? __('View') : null)
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-paper-clip')
                    ->url(fn (Expense $record): ?string => $record->receiptUrl(), shouldOpenInNewTab: true)
                    ->placeholder(__('N/A')),
                TextColumn::make('note')->label(__('Note'))->limit(30)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('expense_type_id')
                    ->label(__('Expense Type'))
                    ->options(fn (): array => ExpenseType::all()->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('payment_type_id')
                    ->label(__('Payment Type'))
                    ->options(fn (): array => PaymentType::all()->pluck('name', 'id')->all())
                    ->preload(),
                BranchField::filter(),
                Filter::make('spent_at')
                    ->schema([
                        DatePicker::make('from')->label(__('From'))->native(false),
                        DatePicker::make('until')->label(__('To'))->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('spent_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('spent_at', '<=', $date))),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                    ForceDeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('No records found'))
            ->defaultSort('spent_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExpenses::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
