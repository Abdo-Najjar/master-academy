<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * One room, one writer at a time.
 *
 * The clash check reads the timetable and then the row is inserted, and those
 * are two separate steps. Two people pressing "save" for the same hall in the
 * same second would both read a free room and both be allowed in — the check
 * cannot see a row that has not been written yet.
 *
 * So the write is serialised per room: whoever gets there first holds the room
 * from the moment the check starts until the row is safely in, and the second
 * one waits and then finds the truth. It is held across processes rather than
 * in memory, because the two people are two requests.
 *
 * The lock carries a short expiry so a process that dies mid-save frees the
 * room by itself rather than blocking the hall until someone restarts things.
 */
class RoomScheduleLock
{
    /** Seconds a lock survives without being released — a crash guard, not a budget. */
    private const TTL = 10;

    /** Seconds a second writer waits for the first before giving up. */
    private const WAIT = 5;

    /**
     * Locks held between a model's `saving` and `saved`, keyed by the row they
     * guard so nested saves of different rows cannot release each other's.
     *
     * @var array<int, Lock>
     */
    private static array $held = [];

    /** Take the room, or fail loudly rather than write into a half-read timetable. */
    public static function acquire(?int $roomId, object $guarding): void
    {
        if (! $roomId) {
            return;
        }

        $lock = Cache::lock('room-schedule:'.$roomId, self::TTL);

        try {
            $lock->block(self::WAIT);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'times' => __('This room is being booked by someone else right now — please try again.'),
            ]);
        }

        self::$held[spl_object_id($guarding)] = $lock;
    }

    /** Give the room back. Safe to call when nothing was taken. */
    public static function release(object $guarding): void
    {
        $key = spl_object_id($guarding);

        if (! isset(self::$held[$key])) {
            return;
        }

        $lock = self::$held[$key];
        unset(self::$held[$key]);

        $lock->release();
    }

    /** Is the room free to take? Used by tests to prove nothing was leaked. */
    public static function isFree(int $roomId): bool
    {
        $lock = Cache::lock('room-schedule:'.$roomId, 1);

        if (! $lock->get()) {
            return false;
        }

        $lock->release();

        return true;
    }
}
