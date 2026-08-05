<?php

namespace App\Filament\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps Filament's resource authorization hooks onto the `<module>.<action>`
 * gate strings declared in PermissionCatalog.
 *
 * These cover page access and anything Filament resolves through the resource.
 * They are NOT enough on their own: Filament actions default to "allowed for
 * everyone" unless given `authorize()`/`visible()`/`hidden()`, so each action
 * must still be wrapped with the matching `canX()` helper below.
 */
trait AuthorizesResourceActions
{
    /** Gate prefix, e.g. `program` for `program.index` / `program.create`. */
    abstract public static function permissionPrefix(): string;

    public static function allows(string $action): bool
    {
        return auth()->user()?->can(static::permissionPrefix().'.'.$action) ?? false;
    }

    /** Closure form for `->visible()` on create actions. */
    public static function canCreateRecords(): Closure
    {
        return fn (): bool => static::canCreate();
    }

    /** Closure form for `->visible()` on edit actions. */
    public static function canUpdateRecords(): Closure
    {
        return fn (): bool => static::allows('update');
    }

    /** Closure form for `->visible()` on delete, force-delete and restore actions. */
    public static function canDeleteRecords(): Closure
    {
        return fn (): bool => static::allows('delete');
    }

    public static function canAccess(): bool
    {
        return static::allows('index');
    }

    public static function canViewAny(): bool
    {
        return static::allows('index');
    }

    public static function canView(Model $record): bool
    {
        return static::allows('index');
    }

    public static function canCreate(): bool
    {
        return static::allows('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::allows('update');
    }

    /** Reordering rewrites sort_order, so it is an update rather than its own gate. */
    public static function canReorder(): bool
    {
        return static::allows('update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::allows('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::allows('delete');
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::allows('delete');
    }

    public static function canForceDeleteAny(): bool
    {
        return static::allows('delete');
    }

    /** Restoring is part of the same trash lifecycle as deleting. */
    public static function canRestore(Model $record): bool
    {
        return static::allows('delete');
    }

    public static function canRestoreAny(): bool
    {
        return static::allows('delete');
    }
}
