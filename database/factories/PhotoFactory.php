<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Photo;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories.Factory<\App\Models\Photo>
 */
class PhotoFactory extends Factory
{
    protected $model = Photo::class;

    public function definition(): array
    {
        $fileName = $this->faker->uuid().'.jpg';

        return [
            'user_id' => User::factory(),
            'client_id' => Client::factory(),
            'project_id' => null,
            'file_path' => 'photos/'.$fileName,
            'caption' => $this->faker->sentence(),
            'gps_lat' => $this->faker->optional()->latitude(),
            'gps_lng' => $this->faker->optional()->longitude(),
            'quality_score' => $this->faker->numberBetween(60, 95),
            'approved' => $this->faker->boolean(70),
            'edited_variants' => null,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn () => [
            'project_id' => $project->id,
            'client_id' => $project->client_id,
        ]);
    }
}

