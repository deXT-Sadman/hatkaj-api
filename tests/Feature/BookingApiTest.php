<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Service $service, array $overrides = []): array
    {
        return [
            'service_id' => $service->id,
            'customer_name' => 'Rahim Uddin',
            'customer_phone' => '+8801712345678',
            'address' => 'House 5, Road 3, Dhaka',
            'scheduled_at' => now()->addDays(2)->setTime(10, 0)->toIso8601String(),
            ...$overrides,
        ];
    }

    public function test_it_creates_a_pending_booking_and_logs_it(): void
    {
        $service = Service::factory()->create();

        $this->postJson('/api/bookings', $this->payload($service))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.service.id', $service->id);

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('booking_status_logs', ['from_status' => null, 'to_status' => 'pending']);
    }

    public function test_it_validates_the_payload(): void
    {
        $inactive = Service::factory()->inactive()->create();

        $this->postJson('/api/bookings', $this->payload($inactive, [
            'customer_name' => '',
            'customer_phone' => 'abc',
            'scheduled_at' => now()->subDay()->toIso8601String(),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_id', 'customer_name', 'customer_phone', 'scheduled_at']);
    }

    public function test_it_rejects_a_booking_at_the_same_time_for_the_same_service(): void
    {
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $this->postJson('/api/bookings', $this->payload($service))->assertCreated();

        $this->postJson('/api/bookings', $this->payload($service))
            ->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_it_rejects_overlapping_slots_but_allows_back_to_back_ones(): void
    {
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $start = now()->addDays(2)->setTime(10, 0);
        $this->postJson('/api/bookings', $this->payload($service, ['scheduled_at' => $start->toIso8601String()]))->assertCreated();

        $this->postJson('/api/bookings', $this->payload($service, ['scheduled_at' => $start->copy()->addMinutes(30)->toIso8601String()]))
            ->assertStatus(409);
        $this->postJson('/api/bookings', $this->payload($service, ['scheduled_at' => $start->copy()->addMinutes(60)->toIso8601String()]))
            ->assertCreated();
    }

    public function test_the_same_time_is_allowed_for_a_different_service(): void
    {
        [$first, $second] = Service::factory()->count(2)->create();
        $this->postJson('/api/bookings', $this->payload($first))->assertCreated();

        $this->postJson('/api/bookings', $this->payload($second))->assertCreated();
    }

    public function test_a_cancelled_booking_frees_its_slot(): void
    {
        $service = Service::factory()->create();
        $id = $this->postJson('/api/bookings', $this->payload($service))->json('data.id');

        $this->patchJson("/api/bookings/{$id}/status", ['status' => 'cancelled'])->assertOk();

        $this->postJson('/api/bookings', $this->payload($service))->assertCreated();
    }

    public function test_it_lists_bookings_with_filters(): void
    {
        Booking::factory()->for($this->user)->withStatus(BookingStatus::Pending)->create();
        Booking::factory()->for($this->user)->withStatus(BookingStatus::Confirmed)->count(2)->create();

        $this->getJson('/api/bookings')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/bookings?status=confirmed')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/bookings?status=bogus')->assertUnprocessable();
    }

    public function test_it_updates_status_along_a_valid_transition_and_logs_it(): void
    {
        $booking = Booking::factory()->for($this->user)->create();

        $this->patchJson("/api/bookings/{$booking->id}/status", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('booking_status_logs', [
            'booking_id' => $booking->id,
            'from_status' => 'pending',
            'to_status' => 'confirmed',
        ]);
    }

    public function test_it_rejects_an_invalid_status_transition(): void
    {
        $booking = Booking::factory()->for($this->user)->create();

        $this->patchJson("/api/bookings/{$booking->id}/status", ['status' => 'completed'])
            ->assertUnprocessable()
            ->assertJsonStructure(['message']);

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_it_returns_404_json_for_an_unknown_booking(): void
    {
        $this->patchJson('/api/bookings/999/status', ['status' => 'confirmed'])->assertNotFound();
    }
}
