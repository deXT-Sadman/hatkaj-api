<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = Service::all();

        foreach ($services as $index => $service) {
            Booking::factory()
                ->for($service)
                ->state([
                    'scheduled_at' => now()->addDays($index + 1)->setTime(10, 0),
                    'status' => BookingStatus::Pending,
                ])
                ->create();
        }
    }
}
