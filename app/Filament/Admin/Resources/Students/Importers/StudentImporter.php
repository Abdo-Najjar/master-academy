<?php

namespace App\Filament\Admin\Resources\Students\Importers;

use App\Models\Student;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Hash;

class StudentImporter extends Importer
{
    protected static ?string $model = Student::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label(__('Name'))
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),

            ImportColumn::make('username')
                ->label(__('Username'))
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),

            ImportColumn::make('password')
                ->label(__('Password'))
                ->rules(['nullable', 'string', 'min:6']),

            ImportColumn::make('student_number')
                ->label(__('Student Number'))
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('email')
                ->label(__('Email'))
                ->rules(['nullable', 'email', 'max:255']),

            ImportColumn::make('ssn')
                ->label(__('SSN'))
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('phone_number')
                ->label(__('Phone'))
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('whatsapp_number')
                ->label(__('WhatsApp'))
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('parent_name')
                ->label(__('Parent Name'))
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('parent_phone')
                ->label(__('Parent Phone'))
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('parent_whatsapp')
                ->label(__('Parent WhatsApp'))
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('dob')
                ->label(__('Date of Birth'))
                ->rules(['nullable', 'date']),
        ];
    }

    public function resolveRecord(): ?Student
    {
        // Look up by username to allow upsert via CSV
        $student = Student::query()
            ->withTrashed()
            ->where('username', $this->data['username'] ?? null)
            ->first();

        if ($student) {
            // Don't overwrite password if not provided in CSV
            if (empty($this->data['password'])) {
                unset($this->data['password']);
            } else {
                $this->data['password'] = Hash::make($this->data['password']);
            }
            return $student;
        }

        $name = $this->data['name'] ?? null;
        $username = $this->data['username'] ?? null;
        if (! $name || ! $username) {
            return null;
        }

        // Hash password OR generate a default of "password"
        $password = ! empty($this->data['password'])
            ? Hash::make($this->data['password'])
            : Hash::make('password');

        return new Student([
            'name' => $name,
            'username' => $username,
            'password' => $password,
            'is_active' => true,
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __(':count students imported successfully.', ['count' => number_format($import->successful_rows)]);

        if ($failed = $import->getFailedRowsCount()) {
            $body .= ' '.__(':count rows failed to import.', ['count' => number_format($failed)]);
        }

        return $body;
    }
}
