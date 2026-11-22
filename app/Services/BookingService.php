<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\SlotConflictException;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    /**
     * Create a pending booking for the user, rejecting it if the slot is already taken.
     *
     * @param  array{service_id: int, customer_name: string, customer_phone: string, address: string, scheduled_at: string, payment_method?: string, notes?: string|null}  $data
     *
     * @throws SlotConflictException
     */
    public function create(User $user, array $data): Booking
    {
        return DB::transaction(function () use ($user, $data): Booking {
            $service = Service::findOrFail($data['service_id']);
            $startsAt = $this->parseStart($data['scheduled_at']);

            if ($this->hasConflict($service, $startsAt, lock: true)) {
                throw new SlotConflictException;
            }

            $booking = new Booking([
                ...$data,
                ...$this->quote($service),
                'scheduled_at' => $startsAt,
                'payment_method' => $data['payment_method'] ?? PaymentMethod::CashOnService,
                'status' => BookingStatus::Pending,
            ]);
            $booking->user()->associate($user);
            $booking->save();

            $booking->statusLogs()->create(['from_status' => null, 'to_status' => BookingStatus::Pending]);

            return $booking;
        });
    }

    /**
     * Build the checkout summary without saving anything.
     *
     * @param  array{service_id: int, customer_name: string, customer_phone: string, address: string, scheduled_at: string, payment_method?: string, notes?: string|null}  $data
     * @return array{service: Service, customer_name: string, customer_phone: string, address: string, scheduled_at: Carbon, payment_method: PaymentMethod, notes: string|null, pricing: array{base_price: string, service_fee: string, total_amount: string}}
     *
     * @throws SlotConflictException
     */
    public function preview(array $data): array
    {
        $service = Service::findOrFail($data['service_id']);
        $startsAt = $this->parseStart($data['scheduled_at']);

        if ($this->hasConflict($service, $startsAt)) {
            throw new SlotConflictException;
        }

        return [
            'service' => $service,
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'address' => $data['address'],
            'scheduled_at' => $startsAt,
            'payment_method' => PaymentMethod::from($data['payment_method'] ?? PaymentMethod::CashOnService->value),
            'notes' => $data['notes'] ?? null,
            'pricing' => $this->quote($service),
        ];
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
     * Price breakdown for a service, computed in cents to avoid float drift.
     *
     * @return array{base_price: string, service_fee: string, total_amount: string}
     */
    public function quote(Service $service): array
    {
        $baseCents = (int) round((float) $service->price * 100);
        $feeCents = (int) round($baseCents * (float) config('booking.service_fee_percent') / 100);

        return [
            'base_price' => number_format($baseCents / 100, 2, '.', ''),
            'service_fee' => number_format($feeCents / 100, 2, '.', ''),
            'total_amount' => number_format(($baseCents + $feeCents) / 100, 2, '.', ''),
        ];
    }

    private function parseStart(string $scheduledAt): Carbon
    {
        return Carbon::parse($scheduledAt)->setTimezone(config('app.timezone'));
    }

    /**
     * A service always lasts the same time, so two bookings of it overlap
     * exactly when their start times are closer than one duration apart.
     */
    private function hasConflict(Service $service, Carbon $startsAt, bool $lock = false): bool
    {
        $duration = $service->duration_minutes;

        return Booking::blockingSlot()
            ->where('service_id', $service->id)
            ->where('scheduled_at', '>', $startsAt->copy()->subMinutes($duration))
            ->where('scheduled_at', '<', $startsAt->copy()->addMinutes($duration))
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->exists();
    }
}
