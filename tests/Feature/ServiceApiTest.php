<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_active_services(): void
    {
        Service::factory()->create(['name' => 'Plumbing']);
        Service::factory()->inactive()->create();

        $this->getJson('/api/services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Plumbing')
            ->assertJsonStructure(['data' => [['id', 'name', 'description', 'price', 'duration_minutes']]]);
    }
}
