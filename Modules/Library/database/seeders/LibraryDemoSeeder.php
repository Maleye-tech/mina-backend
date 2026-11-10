<?php

namespace Modules\Library\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Library\Models\Level;
use Modules\Library\Models\Subject;

/**
 * Catalogue de démonstration prêt pour Postman.
 *
 * Le seeder est idempotent : il s'appuie sur le slug unique des matières et
 * des niveaux pour mettre à jour les lignes existantes au lieu de les
 * dupliquer.
 */
class LibraryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            [
                'slug' => 'anatomie',
                'name' => 'Anatomie',
                'description' => 'Structures du corps humain, repères topographiques et rapports entre organes.',
            ],
            [
                'slug' => 'physiologie',
                'name' => 'Physiologie',
                'description' => 'Fonctionnement normal des grands systèmes et régulation de l’homéostasie.',
            ],
            [
                'slug' => 'pharmacologie',
                'name' => 'Pharmacologie',
                'description' => 'Mécanismes d’action, posologies et effets indésirables des médicaments essentiels.',
            ],
            [
                'slug' => 'pathologies-medicales',
                'name' => 'Pathologies médicales',
                'description' => 'Maladies infectieuses, métaboliques et chroniques fréquentes en Afrique de l’Ouest.',
            ],
            [
                'slug' => 'sante-mentale',
                'name' => 'Santé mentale',
                'description' => 'Troubles psychiatriques courants, repérage, diagnostic et accompagnement.',
            ],
            [
                'slug' => 'nutrition',
                'name' => 'Nutrition',
                'description' => 'Besoins nutritionnels, carences et alimentation adaptée aux contextes locaux.',
            ],
            [
                'slug' => 'epidemiologie',
                'name' => 'Épidémiologie',
                'description' => 'Mesures de fréquence, facteurs de risque et lecture critique des études.',
            ],
            [
                'slug' => 'soins-infirmiers',
                'name' => 'Soins infirmiers',
                'description' => 'Gestes techniques, surveillance clinique et conduites à tenir en pratique.',
            ],
        ];

        foreach ($subjects as $index => $subject) {
            Subject::query()->updateOrCreate(
                ['slug' => $subject['slug']],
                [
                    'name' => $subject['name'],
                    'description' => $subject['description'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }

        $levels = [
            [
                'slug' => 'premiere-annee',
                'name' => '1ère année',
                'description' => 'Première année du cursus de santé.',
            ],
            [
                'slug' => 'deuxieme-annee',
                'name' => '2e année',
                'description' => 'Deuxième année du cursus de santé.',
            ],
            [
                'slug' => 'troisieme-annee',
                'name' => '3e année',
                'description' => 'Troisième année du cursus de santé.',
            ],
            [
                'slug' => 'quatrieme-annee',
                'name' => '4e année',
                'description' => 'Quatrième année du cursus de santé.',
            ],
            [
                'slug' => 'cinquieme-annee',
                'name' => '5e année',
                'description' => 'Cinquième année du cursus de santé.',
            ],
            [
                'slug' => 'sixieme-annee',
                'name' => '6e année',
                'description' => 'Sixième année du cursus de santé.',
            ],
            [
                'slug' => 'septieme-annee',
                'name' => '7e année',
                'description' => 'Septième année du cursus de santé.',
            ],
        ];

        foreach ($levels as $index => $level) {
            Level::query()->updateOrCreate(
                ['slug' => $level['slug']],
                [
                    'name' => $level['name'],
                    'description' => $level['description'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
