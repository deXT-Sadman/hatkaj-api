<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            ['name' => 'AC Servicing', 'description' => 'Cleaning and gas check for split or window AC units.', 'price' => 1200, 'duration_minutes' => 90],
            ['name' => 'Electrical Repair', 'description' => 'Wiring faults, switches, sockets and light fittings.', 'price' => 800, 'duration_minutes' => 60],
            ['name' => 'Plumbing Fix', 'description' => 'Leaking pipes, taps, and drain blockages.', 'price' => 700, 'duration_minutes' => 60],
            ['name' => 'Home Deep Cleaning', 'description' => 'Full-home cleaning including kitchen and bathrooms.', 'price' => 3500, 'duration_minutes' => 240],
            ['name' => 'Appliance Repair', 'description' => 'Fridge, washing machine and microwave diagnostics.', 'price' => 1000, 'duration_minutes' => 90],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(['name' => $service['name']], $service);
        }
    }
}
