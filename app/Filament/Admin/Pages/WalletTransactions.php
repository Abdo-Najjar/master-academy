<?php

namespace App\Filament\Admin\Pages;

use App\Models\PaymentType;
use App\Models\Student;
use App\Models\Trainer;
use BackedEnum;
use Bavix\Wallet\Models\Transaction;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WalletTransactions extends Page implements HasTable
{
    use HasHexaLite, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected string $view = 'filament.admin.pages.wallet-transactions';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('Finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('Payment Operations');
    }

    public function getTitle(): string
    {
        return __('Payment Operations');
    }

    public static function canAccess(): bool
    {
        return hexa()->can('wallet_transactions.index');
    }

    public function defineGates(): array
    {
        return [
            'wallet_transactions.index' => __('View Payment Operations'),
        ];
    }

    public function roleName(): string
    {
        return __('Payment Operations');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()
                    ->with(['payable'])
                    ->whereNull('deleted_at')
                    ->latest('created_at')
            )
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('Operation'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'deposit' ? __('Deposit') : __('Withdraw'))
                    ->color(fn (string $state): string => $state === 'deposit' ? 'success' : 'danger'),
                TextColumn::make('payable_type')
                    ->label(__('Account Type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match (class_basename($state)) {
                        'Student' => __('Student'),
                        'Trainer' => __('Trainer'),
                        default => __(class_basename($state)),
                    })
                    ->color(fn (string $state): string => match (class_basename($state)) {
                        'Student' => 'info',
                        'Trainer' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('payable.name')
                    ->label(__('Name'))
                    ->state(fn (Transaction $record): string => (string) ($record->payable?->name ?? '—')),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->state(fn (Transaction $record): string => '₪' . number_format(abs((float) $record->amountFloat), 2))
                    ->sortable(),
                TextColumn::make('meta.description')
                    ->label(__('Description'))
                    ->limit(40)
                    ->wrap(),
                TextColumn::make('meta.payment_type_id')
                    ->label(__('Payment Type'))
                    ->state(fn (Transaction $record): string => self::paymentTypeName($record->meta['payment_type_id'] ?? null)),
                TextColumn::make('meta.note')
                    ->label(__('Note'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Operation'))
                    ->options([
                        'deposit' => __('Deposit'),
                        'withdraw' => __('Withdraw'),
                    ]),
                SelectFilter::make('payable_type')
                    ->label(__('Account Type'))
                    ->options([
                        Student::class => __('Student'),
                        Trainer::class => __('Trainer'),
                    ]),
                SelectFilter::make('payment_type')
                    ->label(__('Payment Type'))
                    ->options(fn () => PaymentType::all()->pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data): Builder {
                        return filled($data['value'] ?? null)
                            ? $query->where('meta->payment_type_id', $data['value'])
                            : $query;
                    }),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(__('From')),
                        DatePicker::make('until')->label(__('To')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->headerActions([
                Action::make('exportExcel')
                    ->label(__('Export to Excel'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn () => $this->exportExcel()),
            ])
            ->paginated([25, 50, 100])
            ->defaultSort('created_at', 'desc');
    }

    /** Stream an XLSX of the currently filtered table rows. */
    public function exportExcel(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery() ?? $this->getFilteredTableQuery();
        $rows = $query->with('payable')->get();

        $fileName = 'payment-operations-' . now()->format('Y-m-d-Hi') . '.xlsx';

        return response()->streamDownload(function () use ($rows): void {
            $writer = new Writer();
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                '#',
                __('Operation'),
                __('Account Type'),
                __('Name'),
                __('Amount'),
                __('Description'),
                __('Payment Type'),
                __('Note'),
                __('Date'),
            ]));

            foreach ($rows as $t) {
                $writer->addRow(Row::fromValues([
                    $t->id,
                    $t->type === 'deposit' ? __('Deposit') : __('Withdraw'),
                    match (class_basename($t->payable_type)) {
                        'Student' => __('Student'),
                        'Trainer' => __('Trainer'),
                        default => class_basename($t->payable_type),
                    },
                    (string) ($t->payable?->name ?? '—'),
                    round(abs((float) $t->amountFloat), 2),
                    (string) ($t->meta['description'] ?? ''),
                    self::paymentTypeName($t->meta['payment_type_id'] ?? null),
                    (string) ($t->meta['note'] ?? ''),
                    $t->created_at?->format('Y-m-d H:i'),
                ]));
            }

            $writer->close();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private static function paymentTypeName(int|string|null $id): string
    {
        if (! $id) {
            return '—';
        }

        static $types = null;
        $types ??= PaymentType::all()->pluck('name', 'id');

        return (string) ($types->get($id) ?: '—');
    }
}
