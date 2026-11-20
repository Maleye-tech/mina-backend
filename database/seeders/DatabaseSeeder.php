<?php

namespace Database\Seeders;

use App\Models\University;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Assessment\Database\Seeders\AssessmentDemoSeeder;
use Modules\Auth\Database\Seeders\AuthOnboardingSeeder;
use Modules\Auth\Enums\UserRole;
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

        $ucad = University::query()->where('slug', 'ucad')->first();

        User::firstOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'Aminata Diallo',
                'password' => 'password',
                'role' => UserRole::Student,
                'email_verified_at' => now(),
                'education_level' => '3e année',
                'study_field' => 'Médecine',
                'university_id' => $ucad?->id,
                'faculty' => $ucad?->faculties()->value('name'),
                'faculty_id' => $ucad?->faculties()->value('id'),
                'onboarding_completed_at' => now(),
            ],
        );

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Démo',
                'password' => 'password',
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ],
        );
    }
}
