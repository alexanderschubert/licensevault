<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\LicenseType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'vendor' => fake()->company(),
            'license_type' => LicenseType::Subscription,
            'secret' => 'TEST-AAAA-BBBB-CCCC',
            'purchase_date' => today()->subMonths(3),
            'purchase_price' => 99.00,
            'currency' => 'EUR',
            'billing_interval' => BillingInterval::Yearly,
            'expires_at' => today()->addMonths(9),
            'device_limit' => 3,
        ];
    }
}
