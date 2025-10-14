<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories.Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-6 months', 'now');
        $end = $this->faker->boolean() ? $this->faker->dateTimeBetween($start, '+3 months') : null;

        return [
            'client_id' => Client::factory(),
            'name' => $this->faker->catchPhrase(),
            'description' => $this->faker->paragraph(),
            'location' => $this->faker->city(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end?->format('Y-m-d'),
        ];
    }
}

