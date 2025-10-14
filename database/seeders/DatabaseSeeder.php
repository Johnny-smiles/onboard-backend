<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Photo;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $clientRole = Role::firstOrCreate(['name' => 'client']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'client_id' => null,
            ]
        );

        $admin->syncRoles([$adminRole->name]);

        $clients = Client::factory()->count(4)->create();

        $clients->each(function (Client $client) use ($admin, $clientRole) {
            $admin->managedClients()->syncWithoutDetaching($client->id);

            $clientUsers = User::factory()
                ->count(2)
                ->forClient($client)
                ->create();

            $clientUsers->each(fn (User $user) => $user->syncRoles([$clientRole->name]));

            $projects = Project::factory()->count(3)->for($client)->create();
            $uploader = $clientUsers->first();

            Photo::factory()
                ->count(6)
                ->state(function () use ($client, $uploader, $projects) {
                    return [
                        'client_id' => $client->id,
                        'user_id' => $uploader?->id,
                        'project_id' => $projects->isNotEmpty() && fake()->boolean(65)
                            ? $projects->random()->id
                            : null,
                    ];
                })
                ->create();
        });
    }
}
