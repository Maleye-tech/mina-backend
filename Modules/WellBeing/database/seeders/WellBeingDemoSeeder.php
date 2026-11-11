<?php

namespace Modules\WellBeing\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\WellBeing\Enums\AssessmentTestType;
use Modules\WellBeing\Enums\WellbeingCategory;
use Modules\WellBeing\Models\AssessmentTest;
use Modules\WellBeing\Models\EmergencyContact;
use Modules\WellBeing\Models\WellbeingArticle;

/**
 * Contenu de démonstration Mina Care.
 *
 * Le seeder est idempotent : les fiches sont dédupliquées par slug, les
 * tests par couple (titre, type), les questions par texte au sein d'un test
 * et les contacts par nom et numéro. Il peut donc être rejoué sans créer de
 * doublons.
 */
class WellBeingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedArticles();
        $this->seedTests();
        $this->seedEmergencyContacts();
    }

    /**
     * Trois fiches de prévention couvrant les thématiques principales.
     */
    private function seedArticles(): void
    {
        $articles = [
            [
                'title' => 'Comprendre et apaiser le stress au quotidien',
                'category' => WellbeingCategory::Stress,
                'content' => <<<'MARKDOWN'
# Comprendre et apaiser le stress au quotidien

Le stress est une réaction normale de l'organisme face à une situation perçue comme exigeante. Il devient problématique lorsqu'il s'installe durablement et épuise vos ressources.

## Reconnaître les signaux

- Tension dans la nuque, les épaules ou la mâchoire.
- Difficulté à vous concentrer ou à dormir.
- Irritabilité, impatience ou sentiment d'être débordé(e).

## Trois gestes simples

1. **Respirer** : inspirez lentement pendant 4 secondes, retenez 2 secondes, expirez pendant 6 secondes. Répétez cinq fois.
2. **Nommer** : écrivez ce qui dépend de vous et ce qui ne dépend pas de vous dans la situation actuelle.
3. **Récupérer** : protégez un temps sans écran et une activité qui vous ressource, même court.

## Quand demander de l'aide

Si le stress perturbe votre sommeil, vos études ou vos relations pendant plusieurs semaines, parlez-en à un professionnel de santé ou à une personne de confiance. Demander de l'aide est une force.
MARKDOWN,
            ],
            [
                'title' => 'Reconnaître les signes de l’épuisement professionnel',
                'category' => WellbeingCategory::Burnout,
                'content' => <<<'MARKDOWN'
# Reconnaître les signes de l'épuisement professionnel

L'épuisement professionnel, ou burnout, s'installe progressivement. Il associe une fatigue profonde, une perte d'engagement et un sentiment d'efficacité réduit.

## Les signes à surveiller

- Une fatigue qui ne disparaît pas après le repos.
- Un détachement vis-à-vis des études, du travail ou des personnes.
- Un sentiment de ne plus être à la hauteur, malgré vos efforts.
- Des troubles du sommeil, de l'appétit ou de l'humeur.

## Ce qui aide

- **Alléger** : identifiez une demande que vous pouvez reporter ou déléguer cette semaine.
- **Poser des limites** : un horaire de fin, une pause réelle, un « non » respectueux.
- **Parler** : partagez ce que vous vivez avec une personne de confiance ou un professionnel.
- **Reconstruire** : reprenez une activité choisie pour le plaisir, sans objectif de performance.

## Important

L'épuisement n'est pas un manque de volonté. Si les signes persistent ou s'aggravent, consultez un professionnel de santé. Ce contenu est informatif et ne remplace pas un avis médical.
MARKDOWN,
            ],
            [
                'title' => 'Retrouver un sommeil réparateur',
                'category' => WellbeingCategory::Sleep,
                'content' => <<<'MARKDOWN'
# Retrouver un sommeil réparateur

Le sommeil est un pilier de la santé mentale. Il consolide la mémoire, régule les émotions et restaure l'énergie.

## Une routine favorable

- Se coucher et se lever à des horaires réguliers, y compris le week-end.
- Éviter les écrans dans l'heure qui précède le coucher.
- Garder la chambre fraîche, sombre et calme.
- Limiter la caféine après le début d'après-midi.

## Si le sommeil ne vient pas

1. Sortez du lit après environ vingt minutes d'éveil.
2. Faites une activité calme et peu lumineuse.
3. Revenez au lit lorsque la somnolence revient.
4. Notez vos préoccupations sur papier pour les « déposer » avant la nuit.

## Quand consulter

Une difficulté à dormir qui dure plus de trois semaines, s'accompagne d'une fatigue importante ou d'une baisse de moral mérite un avis professionnel. Ce contenu ne constitue pas un diagnostic médical.
MARKDOWN,
            ],
        ];

        foreach ($articles as $definition) {
            WellbeingArticle::query()->updateOrCreate(
                ['slug' => Str::slug($definition['title'])],
                [
                    'title' => $definition['title'],
                    'category' => $definition['category'],
                    'content' => $definition['content'],
                    'is_published' => true,
                ],
            );
        }
    }

    /**
     * Trois tests d'auto-évaluation avec cinq questions chacun.
     */
    private function seedTests(): void
    {
        $options = [
            ['key' => 'a', 'label' => 'Jamais', 'points' => 0],
            ['key' => 'b', 'label' => 'Rarement', 'points' => 1],
            ['key' => 'c', 'label' => 'Parfois', 'points' => 2],
            ['key' => 'd', 'label' => 'Souvent', 'points' => 3],
        ];

        $tests = [
            [
                'title' => 'Auto-évaluation de l’anxiété',
                'type' => AssessmentTestType::Anxiety,
                'description' => 'Cinq questions pour observer votre niveau de tension et d’inquiétude au cours des deux dernières semaines.',
                'questions' => [
                    'Au cours des deux dernières semaines, à quelle fréquence vous êtes-vous senti(e) nerveux(se) ou tendu(e) ?',
                    'À quelle fréquence avez-vous eu du mal à arrêter ou à contrôler vos inquiétudes ?',
                    'À quelle fréquence avez-vous eu du mal à vous détendre ?',
                    'À quelle fréquence vous êtes-vous senti(e) si agité(e) qu’il était difficile de rester en place ?',
                    'À quelle fréquence êtes-vous devenu(e) facilement irritable ?',
                ],
            ],
            [
                'title' => 'Auto-évaluation du stress',
                'type' => AssessmentTestType::Stress,
                'description' => 'Cinq questions pour repérer la charge de stress ressentie au cours du dernier mois.',
                'questions' => [
                    'Au cours du dernier mois, à quelle fréquence vous êtes-vous senti(e) dépassé(e) par les exigences de vos études ou de votre travail ?',
                    'À quelle fréquence avez-vous eu l’impression que les choses s’accumulaient plus vite que vous ne pouviez les gérer ?',
                    'À quelle fréquence avez-vous manqué de temps pour vous reposer ou prendre soin de vous ?',
                    'À quelle fréquence avez-vous eu du mal à dormir à cause de vos préoccupations ?',
                    'À quelle fréquence avez-vous ressenti une tension physique dans la nuque, les épaules ou la mâchoire ?',
                ],
            ],
            [
                'title' => 'Auto-évaluation du bien-être',
                'type' => AssessmentTestType::Wellbeing,
                'description' => 'Cinq questions pour faire le point sur vos ressources et votre élan au cours des deux dernières semaines.',
                'questions' => [
                    'Au cours des deux dernières semaines, à quelle fréquence vous êtes-vous senti(e) calme et détendu(e) ?',
                    'À quelle fréquence vous êtes-vous senti(e) plein(e) d’énergie ?',
                    'À quelle fréquence vous êtes-vous senti(e) proche de vos proches ou de vos amis ?',
                    'À quelle fréquence avez-vous trouvé du plaisir dans vos activités quotidiennes ?',
                    'À quelle fréquence avez-vous eu le sentiment que ce que vous faites a du sens ?',
                ],
            ],
        ];

        foreach ($tests as $definition) {
            $test = AssessmentTest::query()->updateOrCreate(
                [
                    'title' => $definition['title'],
                    'type' => $definition['type'],
                ],
                ['description' => $definition['description']],
            );

            foreach ($definition['questions'] as $questionText) {
                $test->questions()->updateOrCreate(
                    ['question_text' => $questionText],
                    ['options' => $options],
                );
            }
        }
    }

    /**
     * Contacts d'urgence publics et contact officiel Volkeno.
     */
    private function seedEmergencyContacts(): void
    {
        $contacts = [
            [
                'name' => 'SAMU National',
                'role_or_description' => 'Urgences médicales',
                'phone_number' => '1515',
                'address' => 'Sénégal',
                'availability' => '24h/24, 7j/7',
                'is_active' => true,
            ],
            [
                'name' => 'Police Secours',
                'role_or_description' => 'Urgences de police',
                'phone_number' => '17',
                'address' => 'Sénégal',
                'availability' => '24h/24, 7j/7',
                'is_active' => true,
            ],
            [
                'name' => 'Sapeurs-pompiers',
                'role_or_description' => 'Urgences incendie et secours',
                'phone_number' => '18',
                'address' => 'Sénégal',
                'availability' => '24h/24, 7j/7',
                'is_active' => true,
            ],
            [
                'name' => 'Volkeno — Mina Care',
                'role_or_description' => 'Contact officiel Volkeno, partenaire de la plateforme Mina Care',
                'phone_number' => (string) config('wellbeing.emergency_contacts.volkeno_phone'),
                'address' => 'HLM Grand Yoff, Villa n°241, Dakar 11500',
                'availability' => 'Lun–Ven, 9h–18h',
                'is_active' => true,
            ],
        ];

        foreach ($contacts as $definition) {
            EmergencyContact::query()->updateOrCreate(
                [
                    'name' => $definition['name'],
                    'phone_number' => $definition['phone_number'],
                ],
                $definition,
            );
        }
    }
}
