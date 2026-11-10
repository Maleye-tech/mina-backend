<?php

namespace Modules\Assessment\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Assessment\Models\ClinicalCase;
use Modules\Assessment\Models\ClinicalCaseStep;
use Modules\Assessment\Models\Flashcard;
use Modules\Assessment\Models\FlashcardDeck;
use Modules\Assessment\Models\Question;
use Modules\Library\Database\Seeders\LibraryDemoSeeder;
use Modules\Library\Models\Level;
use Modules\Library\Models\Subject;

/**
 * Contenu de démonstration prêt pour Postman.
 *
 * Le seeder est idempotent : les questions sont dédupliquées par empreinte,
 * les paquets et cas cliniques par leur titre, et les éléments enfants par
 * leur position. Il peut donc être rejoué sans créer de doublons.
 */
class AssessmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LibraryDemoSeeder::class);

        $this->seedQuestions();
        $this->seedFlashcardDecks();
        $this->seedClinicalCases();
    }

    /**
     * Huit questions publiées et une question brouillon, avec quatre options chacune.
     */
    private function seedQuestions(): void
    {
        $questions = [
            [
                'subject' => 'sante-mentale',
                'level' => 'troisieme-annee',
                'question' => 'Quel neurotransmetteur est principalement associé à la dépression majeure ?',
                'difficulty' => 'easy',
                'status' => 'published',
                'explanation' => 'La sérotonine joue un rôle central dans la régulation de l’humeur, sans être le seul neurotransmetteur impliqué.',
                'source_reference' => 'Cours de psychiatrie — 3e année',
                'options' => [
                    ['label' => 'La sérotonine', 'correct' => true],
                    ['label' => 'La dopamine'],
                    ['label' => 'L’acétylcholine'],
                    ['label' => 'Le GABA'],
                ],
            ],
            [
                'subject' => 'pharmacologie',
                'level' => 'quatrieme-annee',
                'question' => 'Quelle est la posologie initiale recommandée du paracétamol chez l’adulte ?',
                'difficulty' => 'medium',
                'status' => 'published',
                'explanation' => 'La dose usuelle est de 500 mg à 1 g toutes les 6 à 8 heures, sans dépasser 4 g par jour chez l’adulte sain.',
                'source_reference' => 'Formulaire thérapeutique national',
                'options' => [
                    ['label' => '500 mg à 1 g toutes les 6 à 8 heures', 'correct' => true],
                    ['label' => '2 g toutes les 4 heures'],
                    ['label' => '100 mg une fois par jour'],
                    ['label' => '4 g toutes les 2 heures'],
                ],
            ],
            [
                'subject' => 'anatomie',
                'level' => 'premiere-annee',
                'question' => 'Quel nerf innerve le muscle deltoïde ?',
                'difficulty' => 'easy',
                'status' => 'published',
                'explanation' => 'Le nerf axillaire (C5-C6) traverse l’espace quadrilatère et assure l’innervation motrice du deltoïde.',
                'source_reference' => 'Atlas d’anatomie — membre supérieur',
                'options' => [
                    ['label' => 'Le nerf axillaire', 'correct' => true],
                    ['label' => 'Le nerf radial'],
                    ['label' => 'Le nerf médian'],
                    ['label' => 'Le nerf ulnaire'],
                ],
            ],
            [
                'subject' => 'physiologie',
                'level' => 'deuxieme-annee',
                'question' => 'Quel est le volume courant moyen chez l’adulte au repos ?',
                'difficulty' => 'medium',
                'status' => 'published',
                'explanation' => 'Le volume courant correspond à environ 500 mL par cycle respiratoire au repos chez l’adulte.',
                'source_reference' => 'Physiologie respiratoire — 2e année',
                'options' => [
                    ['label' => 'Environ 500 mL', 'correct' => true],
                    ['label' => 'Environ 150 mL'],
                    ['label' => 'Environ 1 500 mL'],
                    ['label' => 'Environ 3 000 mL'],
                ],
            ],
            [
                'subject' => 'epidemiologie',
                'level' => 'cinquieme-annee',
                'question' => 'Quelle mesure évalue la force d’association entre une exposition et une maladie ?',
                'difficulty' => 'medium',
                'status' => 'published',
                'explanation' => 'L’odds ratio et le risque relatif mesurent l’association ; la prévalence et l’incidence décrivent la fréquence.',
                'source_reference' => 'Épidémiologie descriptive et analytique',
                'options' => [
                    ['label' => 'L’odds ratio', 'correct' => true],
                    ['label' => 'La prévalence'],
                    ['label' => 'L’incidence'],
                    ['label' => 'Le taux de mortalité'],
                ],
            ],
            [
                'subject' => 'nutrition',
                'level' => 'deuxieme-annee',
                'question' => 'Quel nutriment constitue la principale source d’énergie du cerveau ?',
                'difficulty' => 'easy',
                'status' => 'published',
                'explanation' => 'Le glucose est le carburant préférentiel du cerveau ; les corps cétoniques prennent le relais lors d’un jeûne prolongé.',
                'source_reference' => 'Nutrition humaine — bases métaboliques',
                'options' => [
                    ['label' => 'Le glucose', 'correct' => true],
                    ['label' => 'Les protéines'],
                    ['label' => 'Les lipides'],
                    ['label' => 'Les fibres'],
                ],
            ],
            [
                'subject' => 'soins-infirmiers',
                'level' => 'troisieme-annee',
                'question' => 'Quelle est la première action devant un patient inconscient présentant une hypoglycémie sévère ?',
                'difficulty' => 'medium',
                'status' => 'published',
                'explanation' => 'Le patient inconscient ne doit rien absorber par voie orale : le glucagon ou le sérum glucosé sont administrés selon le protocole.',
                'source_reference' => 'Protocole d’urgence infirmier',
                'options' => [
                    ['label' => 'Administrer du glucagon ou du sérum glucosé selon le protocole', 'correct' => true],
                    ['label' => 'Faire boire un verre de jus sucré'],
                    ['label' => 'Attendre la fin de la crise'],
                    ['label' => 'Administrer de l’insuline'],
                ],
            ],
            [
                'subject' => 'pathologies-medicales',
                'level' => 'sixieme-annee',
                'question' => 'Quel signe clinique est cardinal dans le paludisme grave chez l’adulte ?',
                'difficulty' => 'medium',
                'status' => 'published',
                'explanation' => 'Les troubles de la conscience font partie des critères de gravité, avec les convulsions, l’anémie sévère et le syndrome de détresse respiratoire.',
                'source_reference' => 'Guide national de prise en charge du paludisme',
                'options' => [
                    ['label' => 'Les troubles de la conscience', 'correct' => true],
                    ['label' => 'Une éruption cutanée'],
                    ['label' => 'Des arthralgies isolées'],
                    ['label' => 'Une constipation'],
                ],
            ],
            [
                'subject' => 'sante-mentale',
                'level' => 'septieme-annee',
                'question' => 'Quel critère est indispensable au diagnostic du trouble de stress post-traumatique ?',
                'difficulty' => 'hard',
                'status' => 'draft',
                'explanation' => 'Le diagnostic suppose une exposition à un événement traumatique, suivi de symptômes d’intrusion, d’évitement et d’hyperactivation.',
                'source_reference' => 'Manuel de psychotraumatologie',
                'options' => [
                    ['label' => 'L’exposition à un événement traumatique', 'correct' => true],
                    ['label' => 'La présence d’hallucinations'],
                    ['label' => 'Des antécédents familiaux de schizophrénie'],
                    ['label' => 'Une consommation d’alcool'],
                ],
            ],
        ];

        foreach ($questions as $definition) {
            $this->seedQuestion($definition);
        }
    }

    /**
     * Deux paquets publiés et un paquet brouillon, avec trois cartes chacun.
     */
    private function seedFlashcardDecks(): void
    {
        $decks = [
            [
                'subject' => 'sante-mentale',
                'level' => 'troisieme-annee',
                'name' => 'Bases de la santé mentale',
                'description' => 'Repères essentiels sur la dépression, l’anxiété et le stress.',
                'status' => 'published',
                'sort_order' => 1,
                'cards' => [
                    [
                        'front' => 'Qu’est-ce qu’un épisode dépressif majeur ?',
                        'back' => 'Une période d’au moins deux semaines associant humeur dépressive ou anhédonie à au moins quatre symptômes supplémentaires, avec retentissement fonctionnel.',
                        'hint' => 'Penser à la durée minimale et au retentissement.',
                    ],
                    [
                        'front' => 'Citer trois symptômes du trouble anxieux généralisé.',
                        'back' => 'Inquiétude excessive, tension musculaire, troubles du sommeil, irritabilité, difficultés de concentration et fatigue.',
                        'hint' => 'Symptômes psychiques et physiques.',
                    ],
                    [
                        'front' => 'Quelle est la différence entre stress aigu et stress chronique ?',
                        'back' => 'Le stress aigu est une réaction brève et adaptative à un événement ; le stress chronique persiste dans le temps et peut épuiser les ressources de la personne.',
                        'hint' => 'Durée et retentissement.',
                    ],
                ],
            ],
            [
                'subject' => 'pharmacologie',
                'level' => 'quatrieme-annee',
                'name' => 'Pharmacologie essentielle',
                'description' => 'Mécanismes d’action et surveillances des traitements fréquemment prescrits.',
                'status' => 'published',
                'sort_order' => 2,
                'cards' => [
                    [
                        'front' => 'Quel est le mécanisme d’action des anti-inflammatoires non stéroïdiens ?',
                        'back' => 'Ils inhibent les cyclo-oxygénases et réduisent la synthèse des prostaglandines, ce qui explique leurs effets anti-inflammatoires, antalgiques et antipyrétiques.',
                        'hint' => 'Penser aux prostaglandines.',
                    ],
                    [
                        'front' => 'Quels sont les signes d’une intoxication aux opioïdes ?',
                        'back' => 'Myosis, bradypnée, somnolence voire coma et dépression respiratoire ; la naloxone est l’antidote de référence.',
                        'hint' => 'Trois signes classiques et un antidote.',
                    ],
                    [
                        'front' => 'Que signifie la demi-vie plasmatique d’un médicament ?',
                        'back' => 'C’est le temps nécessaire pour que la concentration plasmatique diminue de moitié ; elle guide le rythme d’administration et l’accumulation.',
                        'hint' => 'Une diminution de moitié.',
                    ],
                ],
            ],
            [
                'subject' => 'pathologies-medicales',
                'level' => 'sixieme-annee',
                'name' => 'Urgences médicales',
                'description' => 'Conduites à tenir devant les situations aiguës les plus fréquentes.',
                'status' => 'draft',
                'sort_order' => 3,
                'cards' => [
                    [
                        'front' => 'Quelle est la conduite à tenir devant un choc anaphylactique ?',
                        'back' => 'Appeler à l’aide, allonger la personne, administrer de l’adrénaline intramusculaire sans délai, assurer une oxygénation et une perfusion, puis surveiller.',
                        'hint' => 'Le traitement de première intention est injectable.',
                    ],
                    [
                        'front' => 'Quels signes évoquent une acidocétose diabétique ?',
                        'back' => 'Polyurie, polydipsie, douleurs abdominales, vomissements, respiration de Kussmaul, déshydratation et haleine cétonique.',
                        'hint' => 'Respiration profonde et déshydratation.',
                    ],
                    [
                        'front' => 'Quelle est la première étape devant une convulsion fébrile chez l’enfant ?',
                        'back' => 'Protéger l’enfant, le placer en position latérale de sécurité, assurer la liberté des voies aériennes et évaluer la durée de la convulsion avant toute autre mesure.',
                        'hint' => 'Sécurité et voies aériennes.',
                    ],
                ],
            ],
        ];

        foreach ($decks as $definition) {
            $this->seedDeck($definition);
        }
    }

    /**
     * Deux cas cliniques publiés et un brouillon, avec trois étapes chacun.
     */
    private function seedClinicalCases(): void
    {
        $clinicalCases = [
            [
                'subject' => 'sante-mentale',
                'level' => 'troisieme-annee',
                'title' => 'Trouble dépressif majeur chez une étudiante',
                'presentation' => 'Aminata, 21 ans, étudiante en deuxième année, consulte pour une tristesse persistante depuis six semaines. Elle rapporte une perte d’intérêt, des difficultés de sommeil et un absentéisme croissant.',
                'difficulty' => 'medium',
                'status' => 'published',
                'duration_minutes' => 20,
                'learning_goals' => 'Repérer les critères d’un épisode dépressif majeur, évaluer le risque suicidaire et proposer une prise en charge initiale adaptée.',
                'steps' => [
                    [
                        'prompt' => 'Aminata décrit une tristesse quasi quotidienne et une perte d’intérêt pour ses études.',
                        'question' => 'Quels éléments recherchez-vous à l’interrogatoire ?',
                        'expected_answer' => 'La durée des symptômes, l’anhédonie, les troubles du sommeil et de l’appétit, la fatigue, les idées suicidaires et le retentissement fonctionnel.',
                    ],
                    [
                        'prompt' => 'L’examen retrouve un ralentissement psychomoteur, sans signe neurologique focal.',
                        'question' => 'Quel diagnostic évoquez-vous en priorité ?',
                        'expected_answer' => 'Un épisode dépressif majeur, après avoir éliminé une cause organique, toxique ou médicamenteuse.',
                    ],
                    [
                        'prompt' => 'Aucun risque suicidaire immédiat n’est retrouvé et la patiente accepte un suivi.',
                        'question' => 'Quelle prise en charge initiale proposez-vous ?',
                        'expected_answer' => 'Une psychothérapie de soutien, une évaluation spécialisée et un suivi rapproché ; un antidépresseur selon la gravité et l’accord de la patiente.',
                    ],
                ],
            ],
            [
                'subject' => 'pathologies-medicales',
                'level' => 'sixieme-annee',
                'title' => 'Paludisme grave chez un adulte',
                'presentation' => 'Moussa, 34 ans, est amené aux urgences pour fièvre élevée, vomissements et confusion apparue depuis la veille. Il revient d’une zone rurale endémique.',
                'difficulty' => 'hard',
                'status' => 'published',
                'duration_minutes' => 15,
                'learning_goals' => 'Identifier les critères de gravité du paludisme et initier sans délai le traitement parentéral recommandé.',
                'steps' => [
                    [
                        'prompt' => 'Le patient est fébrile à 39,8 °C et confus ; sa pression artérielle est à 95/60 mmHg.',
                        'question' => 'Quels examens demandez-vous en urgence ?',
                        'expected_answer' => 'Un test de diagnostic rapide et/ou une goutte épaisse, une glycémie, un hémogramme, une fonction rénale et une évaluation de la gravité.',
                    ],
                    [
                        'prompt' => 'La goutte épaisse confirme Plasmodium falciparum avec une parasitémie élevée.',
                        'question' => 'Quels critères de gravité recherchez-vous ?',
                        'expected_answer' => 'Troubles de la conscience, convulsions, anémie sévère, hypoglycémie, insuffisance rénale, détresse respiratoire ou hémorragie.',
                    ],
                    [
                        'prompt' => 'Le patient présente des troubles de la conscience et une hypoglycémie.',
                        'question' => 'Quelle est la conduite thérapeutique immédiate ?',
                        'expected_answer' => 'Hospitalisation en urgence, correction de l’hypoglycémie et artésunate injectable selon le protocole national, avec surveillance rapprochée.',
                    ],
                ],
            ],
            [
                'subject' => 'pathologies-medicales',
                'level' => 'sixieme-annee',
                'title' => 'Acidocétose diabétique révélatrice',
                'presentation' => 'Fatou, 19 ans, consulte pour polyurie, polydipsie, vomissements et douleurs abdominales. Elle présente une respiration profonde et une haleine cétonique.',
                'difficulty' => 'hard',
                'status' => 'draft',
                'duration_minutes' => 25,
                'learning_goals' => 'Reconnaître une acidocétose diabétique, comprendre la priorité de la réhydratation et de l’insulinothérapie, et surveiller les complications.',
                'steps' => [
                    [
                        'prompt' => 'La glycémie capillaire est à 4,2 g/L et la bandelette urinaire montre des cétones.',
                        'question' => 'Quel diagnostic évoquez-vous ?',
                        'expected_answer' => 'Une acidocétose diabétique, à confirmer par une gazométrie montrant une acidose métabolique.',
                    ],
                    [
                        'prompt' => 'La gazométrie révèle un pH à 7,15 et un trou anionique augmenté.',
                        'question' => 'Quelles sont les priorités thérapeutiques ?',
                        'expected_answer' => 'Réhydratation par sérum salé isotonique, insulinothérapie intraveineuse continue et correction prudente du potassium selon la kaliémie.',
                    ],
                    [
                        'prompt' => 'La patiente est admise en unité de soins intensifs.',
                        'question' => 'Quels paramètres surveillez-vous en priorité ?',
                        'expected_answer' => 'La glycémie, la kaliémie, l’équilibre acido-basique, l’état neurologique et la diurèse, afin de prévenir l’hypokaliémie et l’œdème cérébral.',
                    ],
                ],
            ],
        ];

        foreach ($clinicalCases as $definition) {
            $this->seedClinicalCase($definition);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedQuestion(array $definition): void
    {
        $subject = Subject::query()->where('slug', $definition['subject'])->firstOrFail();
        $level = Level::query()->where('slug', $definition['level'])->firstOrFail();

        $fingerprint = hash('sha256', implode('|', [
            'demo',
            $definition['subject'],
            $definition['level'],
            mb_strtolower($definition['question']),
        ]));

        $question = Question::withTrashed()->updateOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'subject_id' => $subject->id,
                'level_id' => $level->id,
                'question' => $definition['question'],
                'question_type' => $definition['question_type'] ?? 'single',
                'difficulty' => $definition['difficulty'] ?? 'medium',
                'status' => $definition['status'] ?? 'draft',
                'explanation' => $definition['explanation'] ?? null,
                'source_reference' => $definition['source_reference'] ?? null,
            ],
        );

        if ($question->trashed()) {
            $question->restore();
        }

        foreach ($definition['options'] as $index => $option) {
            $question->options()->updateOrCreate(
                ['sort_order' => $index + 1],
                [
                    'label' => $option['label'],
                    'is_correct' => $option['correct'] ?? false,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedDeck(array $definition): void
    {
        $subject = Subject::query()->where('slug', $definition['subject'])->firstOrFail();
        $level = Level::query()->where('slug', $definition['level'])->firstOrFail();

        $deck = FlashcardDeck::withTrashed()->updateOrCreate(
            [
                'name' => $definition['name'],
                'subject_id' => $subject->id,
                'level_id' => $level->id,
            ],
            [
                'description' => $definition['description'] ?? null,
                'status' => $definition['status'] ?? 'draft',
                'sort_order' => $definition['sort_order'] ?? 0,
            ],
        );

        if ($deck->trashed()) {
            $deck->restore();
        }

        foreach ($definition['cards'] as $index => $card) {
            $flashcard = Flashcard::withTrashed()->updateOrCreate(
                ['deck_id' => $deck->id, 'sort_order' => $index + 1],
                [
                    'front' => $card['front'],
                    'back' => $card['back'],
                    'hint' => $card['hint'] ?? null,
                ],
            );

            if ($flashcard->trashed()) {
                $flashcard->restore();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedClinicalCase(array $definition): void
    {
        $subject = Subject::query()->where('slug', $definition['subject'])->firstOrFail();
        $level = Level::query()->where('slug', $definition['level'])->firstOrFail();

        $clinicalCase = ClinicalCase::withTrashed()->updateOrCreate(
            [
                'title' => $definition['title'],
                'subject_id' => $subject->id,
                'level_id' => $level->id,
            ],
            [
                'presentation' => $definition['presentation'],
                'difficulty' => $definition['difficulty'] ?? 'medium',
                'status' => $definition['status'] ?? 'draft',
                'duration_minutes' => $definition['duration_minutes'] ?? null,
                'learning_goals' => $definition['learning_goals'] ?? null,
            ],
        );

        if ($clinicalCase->trashed()) {
            $clinicalCase->restore();
        }

        foreach ($definition['steps'] as $index => $step) {
            ClinicalCaseStep::query()->updateOrCreate(
                ['clinical_case_id' => $clinicalCase->id, 'sort_order' => $index + 1],
                [
                    'prompt' => $step['prompt'],
                    'question' => $step['question'],
                    'expected_answer' => $step['expected_answer'],
                ],
            );
        }
    }
}
