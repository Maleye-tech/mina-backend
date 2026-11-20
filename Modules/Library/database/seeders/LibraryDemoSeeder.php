<?php

namespace Modules\Library\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Modules\Library\Models\Content;
use Modules\Library\Models\Level;
use Modules\Library\Models\Podcast;
use Modules\Library\Models\Subject;

/**
 * Catalogue de démonstration prêt pour Postman et le frontend.
 *
 * Le seeder est idempotent : il s'appuie sur le slug unique des matières et
 * des niveaux, et sur le titre des contenus et podcasts, pour mettre à jour
 * les lignes existantes au lieu de les dupliquer.
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
                'color' => '#e11d48',
            ],
            [
                'slug' => 'physiologie',
                'name' => 'Physiologie',
                'description' => 'Fonctionnement normal des grands systèmes et régulation de l’homéostasie.',
                'color' => '#0ea5e9',
            ],
            [
                'slug' => 'pharmacologie',
                'name' => 'Pharmacologie',
                'description' => 'Mécanismes d’action, posologies et effets indésirables des médicaments essentiels.',
                'color' => '#8b5cf6',
            ],
            [
                'slug' => 'pathologies-medicales',
                'name' => 'Pathologies médicales',
                'description' => 'Maladies infectieuses, métaboliques et chroniques fréquentes en Afrique de l’Ouest.',
                'color' => '#f59e0b',
            ],
            [
                'slug' => 'sante-mentale',
                'name' => 'Santé mentale',
                'description' => 'Troubles psychiatriques courants, repérage, diagnostic et accompagnement.',
                'color' => '#14b8a6',
            ],
            [
                'slug' => 'nutrition',
                'name' => 'Nutrition',
                'description' => 'Besoins nutritionnels, carences et alimentation adaptée aux contextes locaux.',
                'color' => '#22c55e',
            ],
            [
                'slug' => 'epidemiologie',
                'name' => 'Épidémiologie',
                'description' => 'Mesures de fréquence, facteurs de risque et lecture critique des études.',
                'color' => '#6366f1',
            ],
            [
                'slug' => 'soins-infirmiers',
                'name' => 'Soins infirmiers',
                'description' => 'Gestes techniques, surveillance clinique et conduites à tenir en pratique.',
                'color' => '#f97316',
            ],
        ];

        foreach ($subjects as $index => $subject) {
            Subject::query()->updateOrCreate(
                ['slug' => $subject['slug']],
                [
                    'name' => $subject['name'],
                    'description' => $subject['description'],
                    'color' => $subject['color'],
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

        $this->seedContents();
        $this->seedPodcasts();
    }

    /**
     * Neuf contenus publiés (cours, fiches de synthèse, résumés).
     */
    private function seedContents(): void
    {
        $contents = [
            [
                'subject' => 'soins-infirmiers',
                'level' => 'troisieme-annee',
                'type' => 'course',
                'title' => 'Électrocardiogramme : lire un tracé en 5 temps',
                'description' => 'Méthode systématique de lecture de l’ECG, du rythme aux troubles de conduction.',
                'body' => <<<'HTML'
<h2>Lecture en 5 temps</h2>
<ol>
<li><strong>Rythme</strong> : le P précède-t-il chaque QRS ? Sinon, recherchez une fibrillation atriale.</li>
<li><strong>Fréquence</strong> : comptez les gros carreaux entre deux R (300, 150, 100, 75, 60).</li>
<li><strong>Axes</strong> : regard I et pied III — divergence ou convergence ?</li>
<li><strong>Ondes</strong> : P, QRS, T — largeur du QRS &lt; 120 ms ?</li>
<li><strong>Lésions</strong> : sus-décalage en territoire, sous-décalage, ondes Q pathologiques.</li>
</ol>
<h2>Les trois pièges fréquents</h2>
<ul>
<li>Un QRS large n’est pas forcément une tachycardie ventriculaire.</li>
<li>Un sus-décalage en DII/DIII/DAF peut être un infarctus du ventricule droit : faites V4R.</li>
<li>Hyperkaliémie : ondes T pointues, QRS élargi, absence de P — urgence biologique.</li>
</ul>
HTML,
            ],
            [
                'subject' => 'physiologie',
                'level' => 'premiere-annee',
                'type' => 'course',
                'title' => 'Transmission nerveuse : du potentiel d’action au réflexe',
                'description' => 'Génération, conduction et transmission synaptique expliquées pas à pas.',
                'body' => <<<'HTML'
<h2>Potentiel d’action</h2>
<p>Le seuil est atteint à −55 mV : les canaux Na⁺ voltage-dépendants s’ouvrent, la dépolarisation est déclenchée. La repolarisation suit par l’ouverture des canaux K⁺.</p>
<h2>Conduction</h2>
<ul>
<li>Plus le diamètre du neurone est grand, plus la conduction est rapide.</li>
<li>La gaine de myéline permet une conduction saltatoire de nœud en nœud.</li>
</ul>
<h2>La synapse</h2>
<p>Le message chimique (acétylcholine, sérotonine…) traverse la fente synaptique et se lie à des récepteurs postsynaptiques : dépolarisation (excitation) ou hyperpolarisation (inhibition).</p>
HTML,
            ],
            [
                'subject' => 'pharmacologie',
                'level' => 'quatrieme-annee',
                'type' => 'course',
                'title' => 'Antibiotiques : les 8 molécules à maîtriser',
                'description' => 'Spectres, posologies et pièges des antibiotiques les plus prescrits en Afrique de l’Ouest.',
                'body' => <<<'HTML'
<h2>Bêtalactamines</h2>
<ul>
<li><strong>Amoxicilline</strong> : première intention en otite et sinusite de l’enfant.</li>
<li><strong>Ceftriaxone</strong> : 1 à 2 g/j, seule céphalosporine IV de référence en ville.</li>
</ul>
<h2>Autres classes</h2>
<ul>
<li><strong>Azithromycine</strong> : 500 mg J1 puis 250 mg — attention à l’allongement du QT.</li>
<li><strong>Métronidazole</strong> : anaérobies et amibiase ; interdiction absolue avec l’alcool.</li>
<li><strong>Ciprofloxacino</strong> : éviter chez l’enfant et la femme enceinte (cartilage).</li>
<li><strong>Vancomycine</strong> : MRSA, titration obligatoire.</li>
<li><strong>Fluconazole</strong> : candidoses, dose unique de 150 mg pour la vulvovaginite.</li>
<li><strong>Artéméther-luméfantrine</strong> : paludisme simple non compliqué, à prendre sur fond gras.</li>
</ul>
HTML,
            ],
            [
                'subject' => 'pathologies-medicales',
                'level' => 'troisieme-annee',
                'type' => 'summary_sheet',
                'title' => 'Signes cardinaux du paludisme grave',
                'description' => 'La conduite à tenir en urgence, rappelée sur une seule page.',
                'body' => <<<'HTML'
<h2>Définition</h2>
<p>Paludisme + un signe de gravité chez un patient de zone d’endémie = <strong>urgence vitale</strong>.</p>
<h2>Les signes de gravité</h2>
<ul>
<li>Altération de la conscience (score de Glasgow &lt; 11).</li>
<li>Convulsions.</li>
<li>Anémie sévère avec détresse respiratoire.</li>
<li>Hypoglycémie (&lt; 0,50 g/L).</li>
<li>Hémoglobinurie foncée, détresse, acidose métabolique.</li>
<li>Insuffisance rénale aiguë, ictère, atteinte de la coagulation.</li>
</ul>
<h2>Prise en charge immédiate</h2>
<p>Artésunate IV 2,4 mg/kg à H0, H12, H24 puis quotidien ; réhydration prudente ; contrôle de la glycémie à H0, H4, H12, H24 ; transfusion si Hb &lt; 5 g/dL ou détresse.</p>
HTML,
            ],
            [
                'subject' => 'nutrition',
                'level' => 'deuxieme-annee',
                'type' => 'summary_sheet',
                'title' => 'Carences en micronutriments : repérage et correction',
                'description' => 'Fer, iode, zinc, vitamine A — signes cliniques et conduite à tenir.',
                'body' => <<<'HTML'
<h2>Fer</h2>
<p>Pâleur des conjonctives, asthénie, cheveux cassants. Ferritine &lt; 15 µg/L = carence confirmée. Correction : sulfate ferreux 1 tablette/j à jeun avec vitamine C pendant 3 mois.</p>
<h2>Iode</h2>
<p>Goitre, crétinisme. Prévention : sel iodé, poisson de mer, algues.</p>
<h2>Zinc</h2>
<p>Retard de croissance, diarrhées récidivantes : 10–20 mg/j pendant 10 à 14 jours.</p>
<h2>Vitamine A</h2>
<p>Xérophthalmie, kératomalacie : 200 000 UI à H0, H24, H48 puis à 4 semaines chez l’enfant de 6 à 59 mois.</p>
HTML,
            ],
            [
                'subject' => 'epidemiologie',
                'level' => 'quatrieme-annee',
                'type' => 'summary_sheet',
                'title' => 'Mesures de fréquence : les calculs clés',
                'description' => 'Incidence, prévalence, risque relatif et odds ratio en une fiche.',
                'body' => <<<'HTML'
<h2>Les formules essentielles</h2>
<ul>
<li><strong>Incidence</strong> = nouveaux cas / population à risque pendant la période.</li>
<li><strong>Prévalence</strong> = cas existants / population totale (P ≈ I × D).</li>
<li><strong>Risque relatif</strong> = incidence exposés / incidence non exposés.</li>
<li><strong>Odds ratio</strong> = (a/b) / (c/d) dans un tableau 2×2.</li>
</ul>
<h2>Lecture critique</h2>
<p>RR = 1 : aucun effet. IC 95 % qui franchit 1 : différence non significative. Le OR surestime le risque quand la maladie est fréquente.</p>
HTML,
            ],
            [
                'subject' => 'anatomie',
                'level' => 'premiere-annee',
                'type' => 'recap',
                'title' => 'Anatomie du cœur : rappel express',
                'description' => 'Valves, coronaires et émergences des gros vaisseaux en 10 minutes.',
                'body' => <<<'HTML'
<h2>Les4 valves</h2>
<ul>
<li>Mitrale (2 feuillets) — oreillette gauche → ventricule gauche.</li>
<li>Tricuspide (3 feuillets) — oreillette droite → ventricule droit.</li>
<li>Aortique et pulmonaire — chacune 3 valves sigmoïdes.</li>
</ul>
<h2>Coronaires</h2>
<p>La coronaire gauche naît du sinus aortique gauche et donne l’interventriculaire antérieure + la circonflexe. La coronaire droite irrigue surtout le ventricule droit et le nœud sinusal.</p>
<h2>Repères</h2>
<p>Bord inférieur = pointe à 5 cm à gauche de la ligne médio-claviculaire gauche, au 5e espace intercostal.</p>
HTML,
            ],
            [
                'subject' => 'sante-mentale',
                'level' => 'cinquieme-annee',
                'type' => 'recap',
                'title' => 'Urgences en santé mentale : les3 situations',
                'description' => 'Agitation aiguë, idées suicidaires, psychose aiguë : que faire, que ne pas faire.',
                'body' => <<<'HTML'
<h2>Idées suicidaires</h2>
<p>Écouter sans minimiser, évaluer le plan et les moyens, retirer l’accès aux moyens létaux, hospitaliser si le pronostic vital est engagé.</p>
<h2>Agitation aiguë</h2>
<p>Environnement calme, une personne qui parle à la fois, contention si danger imminent, halopéridol IM + prométhazine selon protocole, surveillance rapprochée.</p>
<h2>Psychose aiguë</h2>
<p>Pas de confrontation sur le contenu des hallucinations. Orienter vers un centre de santé mentale, rechercher une cause organique (toxicomanie, HIV, syphilis, antécédents).</p>
HTML,
            ],
            [
                'subject' => 'pathologies-medicales',
                'level' => 'quatrieme-annee',
                'type' => 'course',
                'title' => 'Infections urinaires : conduite à tenir',
                'description' => 'Cystite simple, pyélonéphrite et ITU du sujet âgé selon les recommandations locales.',
                'body' => <<<'HTML'
<h2>Cystite simple (femme jeune)</h2>
<p>Nitrofurantoïne 100 mg × 2/j pendant 5 jours ou fosfomycine 3 g en dose unique. Hydratation, éviction des rapports pendant le traitement.</p>
<h2>Pyélonéphrite</h2>
<p>Amoxicilline-acide clavulanique per os si forme non compliquée ; ceftriaxone 1 g IV si signes de gravité ou vomissements. Reculture si persistance à 48 h.</p>
<h2>Sujet âgé</h2>
<p>La bactériurie asymptomatique ne se traite pas. Traiter uniquement les symptômes (délirium, chute, rétention) après ECBU.</p>
HTML,
            ],
        ];

        foreach ($contents as $index => $content) {
            Content::query()->updateOrCreate(
                ['title' => $content['title']],
                [
                    'subject_id' => Subject::query()->where('slug', $content['subject'])->sole()->id,
                    'level_id' => Level::query()->where('slug', $content['level'])->sole()->id,
                    'type' => $content['type'],
                    'description' => $content['description'],
                    'body' => $content['body'],
                    'sort_order' => $index + 1,
                    'is_published' => true,
                ],
            );
        }
    }

    /**
     * Quatre podcasts publiés, avec un fichier audio de démonstration.
     */
    private function seedPodcasts(): void
    {
        $adminId = User::query()->where('role', 'admin')->value('id');

        $podcasts = [
            [
                'subject' => 'soins-infirmiers',
                'level' => 'troisieme-annee',
                'title' => 'ECG : reconnaître un bloc de branche',
                'description' => '8 minutes sur le QRS large, le faux infarctus et les pièges du diagnostic différentiel.',
                'file' => 'ecg-bloc-de-branche.mp3',
                'duration' => 486,
            ],
            [
                'subject' => 'pharmacologie',
                'level' => 'quatrieme-annee',
                'title' => 'Pharmacologie du paludisme : les indispensables',
                'description' => 'Artémisinine, résistances et posologies selon le poids, expliquées simplement.',
                'file' => 'pharmacologie-paludisme.mp3',
                'duration' => 612,
            ],
            [
                'subject' => 'pathologies-medicales',
                'level' => 'cinquieme-annee',
                'title' => 'Auscultation cardiaque : les4 bruits à reconnaître',
                'description' => 'Sifflement, souffle, galop — où placer le stéthoscope et que faire ensuite.',
                'file' => 'auscultation-cardiaque.mp3',
                'duration' => 545,
            ],
            [
                'subject' => 'sante-mentale',
                'level' => 'quatrieme-annee',
                'title' => 'Gérer l’anxiété pendant les stages cliniques',
                'description' => 'Outils concrets pour l’anxiété de performance et l’épuisement étudiant.',
                'file' => 'anxiete-stage.mp3',
                'duration' => 398,
            ],
        ];

        if (! app()->environment('testing')) {
            $this->ensureDemoAudio($podcasts);
        }

        Podcast::withoutEvents(function () use ($podcasts, $adminId): void {
            foreach ($podcasts as $podcast) {
                Podcast::query()->updateOrCreate(
                    ['title' => $podcast['title']],
                    [
                        'subject_id' => Subject::query()->where('slug', $podcast['subject'])->sole()->id,
                        'level_id' => Level::query()->where('slug', $podcast['level'])->sole()->id,
                        'description' => $podcast['description'],
                        'original_path' => 'podcast-audio/'.$podcast['file'],
                        'compressed_path' => 'podcast-audio/'.$podcast['file'],
                        'compression_status' => 'done',
                        'mime_type' => 'audio/mpeg',
                        'size_bytes' => 239058,
                        'duration_seconds' => $podcast['duration'],
                        'uploaded_by' => $adminId,
                        'is_published' => true,
                    ],
                );
            }
        });
    }

    /**
     * Génère de vrais fichiers MP3 (silence ~15 s) sur le disque public,
     * nécessaires à la lecture audio côté frontend.
     *
     * @param  array<int, array<string, mixed>>  $podcasts
     */
    private function ensureDemoAudio(array $podcasts): void
    {
        $disk = Storage::disk('public');
        $frame = "\xFF\xFB\x90\x44".str_repeat("\x00", 413);

        foreach ($podcasts as $podcast) {
            $path = 'podcast-audio/'.$podcast['file'];

            if (! $disk->exists($path)) {
                $disk->put($path, str_repeat($frame, 574));
            }
        }
    }
}
