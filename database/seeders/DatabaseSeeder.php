<?php

namespace Database\Seeders;

use App\Models\Client;
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

        $client = Client::firstOrCreate(
            ['name' => 'Demo Co'],
            [
                'contact_email' => 'admin@example.com',
                'brand_color' => '#0b5fff',
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'client_id' => $client->id,
            ]
        );

        $admin->syncRoles([$adminRole->name]);

        $clientRoleName = $clientRole->name;

        User::factory()->count(3)->create()->each(function (User $user) use ($clientRoleName, $client) {
            $user->update(['role' => 'client', 'client_id' => $client->id]);
            $user->syncRoles([$clientRoleName]);
        });
    }
}
