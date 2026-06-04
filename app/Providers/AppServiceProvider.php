<?php

namespace App\Providers;

use App\Listeners\RecordLoginActivity;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(Login::class, RecordLoginActivity::class);

        // Super admin: the admin User with ID 1 bypasses every permission gate
        // (HexaLite gates resolve through the Gate facade, so this covers them too).
        // Scoped to the User model so a Student/Trainer that happens to have id 1
        // on another guard does not gain access.
        Gate::before(function ($user) {
            return ($user instanceof User && (int) $user->getKey() === 1) ? true : null;
        });

        // Project-wide default: show an em dash for empty table cells / detail
        // entries. Columns that set their own placeholder keep it.
        TextColumn::configureUsing(fn (TextColumn $column) => $column->placeholder('—'));
        TextEntry::configureUsing(fn (TextEntry $entry) => $entry->placeholder('—'));

        // Render all table numbers/money with Western (English) digits and a
        // comma grouping instead of the Arabic-Indic numerals the `ar` locale
        // would produce. Money decimals are dropped per-column via decimalPlaces.
        Table::configureUsing(fn (Table $table) => $table->defaultNumberLocale('en'));
    }
}
