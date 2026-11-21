<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\SlotConflictException;
use App\Models\Booking;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    /**
     * Create a pending booking, rejecting it if the slot is already taken.
     *
     * @param  array{service_id: int, customer_name: string, customer_phone: string, address: string, scheduled_at: string, notes?: string|null}  $data
     *
     * @throws SlotConflictException
     */
    public function create(array $data): Booking
    {
        return DB::transaction(function () use ($data): Booking {
            $service = Service::findOrFail($data['service_id']);
            $startsAt = Carbon::parse($data['scheduled_at'])->setTimezone(config('app.timezone'));

            if ($this->hasConflict($service, $startsAt)) {
                throw new SlotConflictException;
            }

            $booking = Booking::create([
                ...$data,
                'scheduled_at' => $startsAt,
                'status' => BookingStatus::Pending,
            ]);

            $booking->statusLogs()->create(['from_status' => null, 'to_status' => BookingStatus::Pending]);

            return $booking;
        });
    }

    /**
     * Move a booking to a new status and record the change.
     *
     * @throws InvalidStatusTransitionException
     */
    public function changeStatus(Booking $booking, BookingStatus $next): Booking
    {
        return DB::transaction(function () use ($booking, $next): Booking {
            $current = $booking->status;

            if (! $current->canTransitionTo($next)) {
                throw new InvalidStatusTransitionException($current, $next);
            }

            $booking->update(['status' => $next]);
            $booking->statusLogs()->create(['from_status' => $current, 'to_status' => $next]);

            return $booking;
        });
    }

    /**
     * A service always lasts the same time, so two bookings of it overlap
     * exactly when their start times are closer than one duration apart.
     */
    private function hasConflict(Service $service, Carbon $startsAt): bool
    {
        $duration = $service->duration_minutes;

        return Booking::blockingSlot()
            ->where('service_id', $service->id)
            ->where('scheduled_at', '>', $startsAt->copy()->subMinutes($duration))
            ->where('scheduled_at', '<', $startsAt->copy()->addMinutes($duration))
            ->lockForUpdate()
            ->exists();
    }
}
