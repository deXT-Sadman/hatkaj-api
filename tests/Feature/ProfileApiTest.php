<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
        $this->putJson('/api/profile', ['name' => 'X'])->assertUnauthorized();
    }

    public function test_it_shows_the_authenticated_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password');
    }

    public function test_it_updates_name_phone_and_default_address(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/profile', [
            'name' => 'New Name',
            'phone' => '+8801999888777',
            'default_address' => '99 New Street',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.phone', '+8801999888777');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'default_address' => '99 New Street']);
    }

    public function test_it_ignores_email_changes_and_validates_phone(): void
    {
        $user = User::factory()->create(['email' => 'keep@example.com']);
        Sanctum::actingAs($user);

        $this->putJson('/api/profile', ['email' => 'hijack@example.com', 'phone' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->putJson('/api/profile', ['email' => 'hijack@example.com', 'name' => 'Ok'])->assertOk();

        $this->assertSame('keep@example.com', $user->fresh()->email);
    }
}
