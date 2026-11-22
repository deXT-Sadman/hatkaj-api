<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function registrationPayload(): array
    {
        return [
            'name' => 'Rahim Uddin',
            'email' => 'rahim@example.com',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'phone' => '+8801712345678',
            'default_address' => '12 Lake Road, Dhaka',
        ];
    }

    public function test_it_registers_a_user_and_returns_a_token(): void
    {
        $this->postJson('/api/register', $this->registrationPayload())
            ->assertCreated()
            ->assertJsonPath('user.email', 'rahim@example.com')
            ->assertJsonPath('user.phone', '+8801712345678')
            ->assertJsonMissingPath('user.password')
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'phone', 'default_address'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'rahim@example.com']);
    }

    public function test_registration_validates_input(): void
    {
        User::factory()->create(['email' => 'rahim@example.com']);

        $this->postJson('/api/register', [
            ...$this->registrationPayload(),
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_it_logs_in_with_valid_credentials(): void
    {
        User::factory()->create(['email' => 'rahim@example.com', 'password' => 'secret-pass-1']);

        $this->postJson('/api/login', ['email' => 'rahim@example.com', 'password' => 'secret-pass-1'])
            ->assertOk()
            ->assertJsonPath('user.email', 'rahim@example.com')
            ->assertJsonStructure(['token']);
    }

    public function test_it_rejects_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'rahim@example.com', 'password' => 'secret-pass-1']);

        $this->postJson('/api/login', ['email' => 'rahim@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current')->plainTextToken;
        $user->createToken('other');

        $this->withToken($current)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'current']);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}
