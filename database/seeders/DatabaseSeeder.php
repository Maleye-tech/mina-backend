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
        $this->call(\Modules\Subscriptions\Database\Seeders\SubscriptionsDatabaseSeeder::class);
        $this->call(\Modules\Appointments\Database\Seeders\AppointmentsDemoSeeder::class);
        $this->call(\Modules\Events\Database\Seeders\EventsDemoSeeder::class);

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
            ['email' => 'ambassador@example.com'],
            [
                'name' => 'Moussa Sarr',
                'password' => 'password',
                'role' => UserRole::Ambassador,
                'email_verified_at' => now(),
                'education_level' => '4e année',
                'study_field' => 'Pharmacie',
                'university_id' => $ucad?->id,
                'faculty' => $ucad?->faculties()->value('name'),
                'faculty_id' => $ucad?->faculties()->value('id'),
                'is_ambassador_verified' => true,
                'ambassador_training_date' => now()->subMonths(3),
                'onboarding_completed_at' => now(),
            ],
        );

        $proUser = User::firstOrCreate(
            ['email' => 'professional@example.com'],
            [
                'name' => 'Dr. Cheikh Ndiaye',
                'password' => 'password',
                'role' => UserRole::Professional,
                'email_verified_at' => now(),
            ],
        );

        \Modules\Appointments\Models\AppointmentProfessional::firstOrCreate(
            ['user_id' => $proUser->id],
            [
                'title' => 'Psychologue clinicien & psychothérapeute',
                'bio' => 'Spécialiste de la santé mentale étudiante et de la gestion du stress universitaire.',
                'phone' => '+221 77 123 45 67',
                'consultation_duration_minutes' => 45,
                'is_available' => true,
            ],
        );

        $crous = \Modules\Subscriptions\Models\PartnerInstitution::where('code', 'CROUS-DAKAR')->first();

        User::firstOrCreate(
            ['email' => 'partner@example.com'],
            [
                'name' => 'Mme Fatou Sow',
                'password' => 'password',
                'role' => UserRole::Partner,
                'email_verified_at' => now(),
                'partner_institution_id' => $crous?->id,
                'partner_position' => 'Directrice des œuvres sociales & santé étudiante (CROUS)',
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
