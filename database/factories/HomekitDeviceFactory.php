<?php

namespace Database\Factories;

use App\Enums\HomekitStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class HomekitDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'manufacturer' => 'Testhersteller',
            'model' => 'TM-1',
            'room' => 'Wohnzimmer',
            'status' => HomekitStatus::Active,
            'protocol' => 'homekit',
            'setup_code' => '000-00-000',
            'qr_payload' => 'X-HM://000000000TST0',
        ];
    }
}
