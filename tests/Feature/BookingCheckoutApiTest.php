<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingCheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Service $service, array $overrides = []): array
    {
        return [
            'service_id' => $service->id,
            'scheduled_at' => now()->addDays(2)->setTime(10, 0)->toIso8601String(),
            ...$overrides,
        ];
    }

    public function test_booking_routes_require_authentication(): void
    {
        $booking = Booking::factory()->create();

        $this->getJson('/api/bookings')->assertUnauthorized();
        $this->postJson('/api/bookings')->assertUnauthorized();
        $this->postJson('/api/bookings/preview')->assertUnauthorized();
        $this->patchJson("/api/bookings/{$booking->id}/status", ['status' => 'confirmed'])->assertUnauthorized();
    }

    public function test_it_lists_only_the_authenticated_users_bookings(): void
    {
        $user = User::factory()->create();
        Booking::factory()->for($user)->count(2)->create();
        Booking::factory()->count(3)->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/bookings')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_it_binds_the_booking_to_the_user_and_snapshots_the_price(): void
    {
        $user = User::factory()->create();
        $service = Service::factory()->create(['price' => '200.00']);
        Sanctum::actingAs($user);

        $this->postJson('/api/bookings', $this->payload($service, [
            'customer_name' => 'Rahim',
            'customer_phone' => '+8801712345678',
            'address' => '5 Road, Dhaka',
            'payment_method' => 'demo_wallet',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.payment_method', 'demo_wallet')
            ->assertJsonPath('data.pricing.base_price', '200.00')
            ->assertJsonPath('data.pricing.service_fee', '10.00')
            ->assertJsonPath('data.pricing.total_amount', '210.00');

        $booking = Booking::sole();
        $this->assertSame($user->id, $booking->user_id);

        $service->update(['price' => '999.00']);
        $this->assertSame('210.00', $booking->fresh()->total_amount);
    }

    public function test_contact_details_default_to_the_profile_and_payment_to_cash(): void
    {
        $user = User::factory()->create([
            'name' => 'Profile Name',
            'phone' => '+8801700000000',
            'default_address' => '1 Profile Street',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/bookings', $this->payload(Service::factory()->create()))
            ->assertCreated()
            ->assertJsonPath('data.customer_name', 'Profile Name')
            ->assertJsonPath('data.customer_phone', '+8801700000000')
            ->assertJsonPath('data.address', '1 Profile Street')
            ->assertJsonPath('data.payment_method', 'cash_on_service');
    }

    public function test_it_requires_contact_details_when_the_profile_has_none(): void
    {
        Sanctum::actingAs(User::factory()->create(['phone' => null, 'default_address' => null]));

        $this->postJson('/api/bookings', $this->payload(Service::factory()->create()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_phone', 'address']);
    }

    public function test_it_rejects_an_unknown_payment_method(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', $this->payload(Service::factory()->create(), ['payment_method' => 'bitcoin']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_preview_returns_the_summary_without_saving(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $service = Service::factory()->create(['price' => '150.00']);

        $this->postJson('/api/bookings/preview', $this->payload($service))
            ->assertOk()
            ->assertJsonPath('data.service.id', $service->id)
            ->assertJsonPath('data.pricing.base_price', '150.00')
            ->assertJsonPath('data.pricing.service_fee', '7.50')
            ->assertJsonPath('data.pricing.total_amount', '157.50')
            ->assertJsonPath('data.payment_method', 'cash_on_service')
            ->assertJsonStructure(['data' => ['scheduled_at', 'address', 'customer_name', 'customer_phone']]);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_preview_reports_a_slot_conflict(): void
    {
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $start = now()->addDays(2)->setTime(10, 0);
        Booking::factory()->for($service)->create(['scheduled_at' => $start]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings/preview', $this->payload($service, ['scheduled_at' => $start->toIso8601String()]))
            ->assertStatus(409);
    }

    public function test_a_user_cannot_change_another_users_booking(): void
    {
        $booking = Booking::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/bookings/{$booking->id}/status", ['status' => 'confirmed'])->assertForbidden();
    }
}
