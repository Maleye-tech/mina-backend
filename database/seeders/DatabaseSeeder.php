<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Assessment\Database\Seeders\AssessmentDemoSeeder;
use Modules\Auth\Database\Seeders\AuthOnboardingSeeder;
use Modules\Library\Database\Seeders\LibraryDemoSeeder;
use Modules\WellBeing\Database\Seeders\WellBeingDemoSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AuthOnboardingSeeder::class);
        $this->call(LibraryDemoSeeder::class);
        $this->call(AssessmentDemoSeeder::class);
        $this->call(WellBeingDemoSeeder::class);

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
            ],
        );
    }
}
