<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ImageColumn;
use Illuminate\Contracts\Support\Htmlable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Adds a "Export to Excel" header action to any List/Manage records page.
 * It streams the currently visible table columns for the filtered + sorted
 * query into an XLSX file using OpenSpout (no queue required).
 */
trait ExportsTableRecords
{
    protected function tableExportAction(): Action
    {
        return Action::make('exportExcel')
            ->label(__('Export to Excel'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->action(fn (): StreamedResponse => $this->exportTableToExcel());
    }

    public function exportTableToExcel(): StreamedResponse
    {
        /** @var array<int, Column> $columns */
        $columns = array_values(array_filter(
            $this->getTable()->getVisibleColumns(),
            fn (Column $column): bool => ! $column instanceof ImageColumn,
        ));

        $records = $this->getFilteredSortedTableQuery()->get();

        return response()->streamDownload(function () use ($columns, $records): void {
            $writer = new Writer();
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues(array_map(
                fn (Column $column): string => $this->stringifyExportValue($column->getLabel()),
                $columns,
            )));

            foreach ($records as $record) {
                $cells = [];
                foreach ($columns as $column) {
                    $cells[] = $this->exportCellValue($column, $record);
                }
                $writer->addRow(Row::fromValues($cells));
            }

            $writer->close();
        }, $this->exportFileName(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function exportFileName(): string
    {
        $base = 'export';

        if (method_exists(static::class, 'getResource') && ($resource = static::getResource())) {
            $base = (string) str(class_basename($resource))->beforeLast('Resource')->kebab();
        }

        return $base.'-'.now()->format('Y-m-d-Hi').'.xlsx';
    }

    protected function exportCellValue(Column $column, mixed $record): string
    {
        try {
            $column->record($record);
            $state = $column->getState();

            if (method_exists($column, 'formatState')) {
                $state = $column->formatState($state);
            }

            return $this->stringifyExportValue($state);
        } catch (\Throwable) {
            return '';
        }
    }

    protected function stringifyExportValue(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        if ($value === true) {
            return '✓';
        }

        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn ($v): string => $this->stringifyExportValue($v), $value));
        }

        if ($value instanceof Htmlable) {
            $value = $value->toHtml();
        }

        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5));
    }
}
