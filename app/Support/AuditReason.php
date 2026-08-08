<?php

namespace App\Support;

/**
 * Carries the operator's stated reason for a change from the UI down to the
 * activity log entry that the model events write.
 *
 * The reason cannot travel on the model itself (it is not a column), and the
 * activity log is written deep inside Eloquent's event chain, so it is stashed
 * for the duration of the request and picked up by the `Activity` creating hook
 * registered in AppServiceProvider.
 */
class AuditReason
{
    protected static ?string $reason = null;

    public static function set(?string $reason): void
    {
        $reason = is_string($reason) ? trim($reason) : null;

        static::$reason = $reason !== '' ? $reason : null;
    }

    public static function get(): ?string
    {
        return static::$reason;
    }

    public static function forget(): void
    {
        static::$reason = null;
    }

    /**
     * Run a callback with the given reason attached to everything it logs.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function using(?string $reason, callable $callback)
    {
        $previous = static::$reason;
        static::set($reason);

        try {
            return $callback();
        } finally {
            static::$reason = $previous;
        }
    }
}
