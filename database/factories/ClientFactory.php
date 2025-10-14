<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'contact_email' => $this->faker->unique()->companyEmail(),
            'contact_phone' => $this->faker->phoneNumber(),
            'logo_url' => $this->faker->imageUrl(320, 320, 'business', true),
            'brand_color' => $this->faker->hexColor(),
            'watermark_enabled' => $this->faker->boolean(40),
            'notes' => $this->faker->sentences(2, true),
        ];
    }
}

