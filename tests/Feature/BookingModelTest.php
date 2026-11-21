<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\Service;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_belongs_to_service_and_casts_status_to_enum(): void
    {
        $booking = Booking::factory()->create();

        $this->assertInstanceOf(Service::class, $booking->service);
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertTrue($booking->scheduled_at->isFuture());
    }

    public function test_service_has_many_bookings_and_active_scope_filters(): void
    {
        $service = Service::factory()->has(Booking::factory()->count(2))->create();
        Service::factory()->inactive()->create();

        $this->assertCount(2, $service->bookings);
        $this->assertCount(1, Service::active()->get());
    }

    public function test_blocking_slot_scope_ignores_cancelled_and_completed_bookings(): void
    {
        Booking::factory()->withStatus(BookingStatus::Pending)->create();
        Booking::factory()->withStatus(BookingStatus::Confirmed)->create();
        Booking::factory()->withStatus(BookingStatus::Cancelled)->create();
        Booking::factory()->withStatus(BookingStatus::Completed)->create();

        $this->assertCount(2, Booking::blockingSlot()->get());
    }

    public function test_status_transitions_follow_the_allowed_flow(): void
    {
        $this->assertTrue(BookingStatus::Pending->canTransitionTo(BookingStatus::Confirmed));
        $this->assertFalse(BookingStatus::Pending->canTransitionTo(BookingStatus::Completed));
        $this->assertFalse(BookingStatus::Cancelled->canTransitionTo(BookingStatus::Pending));
    }

    public function test_status_logs_belong_to_booking_and_are_deleted_with_it(): void
    {
        $log = BookingStatusLog::factory()->create();
        $booking = $log->booking;

        $this->assertSame(BookingStatus::Confirmed, $log->to_status);
        $this->assertCount(1, $booking->statusLogs);

        $booking->delete();

        $this->assertDatabaseCount('booking_status_logs', 0);
    }

    public function test_service_with_bookings_cannot_be_deleted(): void
    {
        $booking = Booking::factory()->create();

        $this->expectException(QueryException::class);

        $booking->service->delete();
    }
}
