<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Statuses that still occupy a time slot. Cancelled bookings free it up.
     *
     * @return array<int, self>
     */
    public static function blockingSlot(): array
    {
        return [self::Pending, self::Confirmed];
    }

    /**
     * Allowed next statuses (used by the status-update endpoint in Checkpoint 2).
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
