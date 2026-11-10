<?php

namespace Modules\Auth\Database\Seeders;

use App\Models\Faculty;
use App\Models\OnboardingQuestion;
use App\Models\University;
use Illuminate\Database\Seeder;

class AuthOnboardingSeeder extends Seeder
{
    public function run(): void
    {
        $universities = [
            [
                'slug' => 'ucad',
                'name' => 'Université Cheikh Anta Diop (UCAD)',
                'city' => 'Dakar',
                'type' => 'public',
                'website_url' => 'https://fmpos.ucad.sn/',
                'sort_order' => 1,
                'faculties' => ['Faculté de Médecine, de Pharmacie et d’Odonto-stomatologie (FMPOS)'],
            ],
            [
                'slug' => 'uidt',
                'name' => 'Université Iba Der Thiam (UIDT)',
                'city' => 'Thiès',
                'type' => 'public',
                'website_url' => null,
                'sort_order' => 2,
                'faculties' => ['Unité de Formation et de Recherche (UFR) Santé'],
            ],
            [
                'slug' => 'uasz',
                'name' => 'Université Assane Seck (UASZ)',
                'city' => 'Ziguinchor',
                'type' => 'public',
                'website_url' => 'https://uasz.sn/ufr-sciences-de-la-sante/',
                'sort_order' => 3,
                'faculties' => ['Unité de Formation et de Recherche (UFR) Sciences de la Santé'],
            ],
            [
                'slug' => 'ugb',
                'name' => 'Université Gaston Berger (UGB)',
                'city' => 'Saint-Louis',
                'type' => 'public',
                'website_url' => null,
                'sort_order' => 4,
                'faculties' => ['Unité de Formation et de Recherche (UFR) Sciences de la Santé'],
            ],
            [
                'slug' => 'st-christopher',
                'name' => 'Université St. Christopher',
                'city' => 'Dakar',
                'type' => 'private',
                'website_url' => 'https://www.universitestchristopher.net/',
                'sort_order' => 5,
                'faculties' => ['Faculté de Médecine'],
            ],
            [
                'slug' => 'ussd',
                'name' => 'Université des Sciences de la Santé de Dakar (USSD)',
                'city' => 'Dakar',
                'type' => 'private',
                'website_url' => null,
                'sort_order' => 6,
                'faculties' => ['Faculté de Médecine'],
            ],
            [
                'slug' => 'upsd',
                'name' => 'Université Privée de Pr Samba Diallo (UPSD)',
                'city' => 'Dakar',
                'type' => 'private',
                'website_url' => 'https://ipformed.sn/',
                'sort_order' => 7,
                'faculties' => ['Médecine générale'],
            ],
            [
                'slug' => 'euromed',
                'name' => 'Euromed Université',
                'city' => 'Dakar',
                'type' => 'private',
                'website_url' => null,
                'sort_order' => 8,
                'faculties' => ['Faculté des Sciences de la Santé'],
            ],
        ];

        foreach ($universities as $universityData) {
            $facultyNames = $universityData['faculties'];
            unset($universityData['faculties']);

            $university = University::updateOrCreate(
                ['slug' => $universityData['slug']],
                [...$universityData, 'is_partner' => true],
            );

            foreach ($facultyNames as $facultyName) {
                Faculty::updateOrCreate(
                    ['university_id' => $university->id, 'name' => $facultyName],
                    [],
                );
            }
        }

        $questions = [
            ['key' => 'education_level', 'label' => 'Quel est votre niveau d’études ?', 'input_type' => 'text', 'sort_order' => 1],
            ['key' => 'study_field', 'label' => 'Quelle est votre filière ?', 'input_type' => 'text', 'sort_order' => 2],
            ['key' => 'university_id', 'label' => 'Dans quelle université étudiez-vous ?', 'input_type' => 'university', 'sort_order' => 3],
            ['key' => 'faculty_id', 'label' => 'Quelle est votre faculté ou école ?', 'input_type' => 'faculty', 'sort_order' => 4],
        ];

        foreach ($questions as $question) {
            OnboardingQuestion::firstOrCreate(
                ['key' => $question['key']],
                [...$question, 'is_required' => true, 'is_active' => true],
            );
        }
    }
}
