<?php

return [
    'name' => 'WellBeing',

    /*
    |--------------------------------------------------------------------------
    | Avertissement obligatoire
    |--------------------------------------------------------------------------
    |
    | Renvoyé avec chaque résultat d'auto-évaluation. Ce texte est un
    | impératif produit : il ne doit jamais être retiré des réponses.
    |
    */
    'disclaimer' => "Ce n'est pas un diagnostic médical.",

    /*
    |--------------------------------------------------------------------------
    | Tranches de score et retours immédiats
    |--------------------------------------------------------------------------
    |
    | Les tranches sont exprimées en pourcentage du score maximal possible
    | pour un test donné. La première tranche dont `max_percentage` est
    | supérieure ou égale au pourcentage obtenu est retenue.
    |
    | Pour l'anxiété et le stress, un score élevé traduit une tension plus
    | importante. Pour le bien-être, un score élevé traduit au contraire un
    | état plus favorable.
    |
    */
    'scoring' => [
        'anxiety' => [
            [
                'max_percentage' => 33,
                'level' => 'low',
                'feedback' => "Vos réponses suggèrent un niveau d'anxiété plutôt faible en ce moment. Continuez à prendre soin de votre sommeil, de votre respiration et de vos temps de récupération.",
            ],
            [
                'max_percentage' => 66,
                'level' => 'moderate',
                'feedback' => "Vos réponses suggèrent un niveau d'anxiété modéré. Des pauses régulières, une activité physique douce et un temps d'échange avec une personne de confiance peuvent vous aider. Si cette tension persiste, parlez-en à un professionnel.",
            ],
            [
                'max_percentage' => 100,
                'level' => 'high',
                'feedback' => "Vos réponses suggèrent un niveau d'anxiété élevé. Vous n'êtes pas seul(e) : nous vous encourageons à en parler rapidement à un professionnel de santé ou à une personne de confiance, et à contacter un service d'écoute si vous en ressentez le besoin.",
            ],
        ],
        'stress' => [
            [
                'max_percentage' => 33,
                'level' => 'low',
                'feedback' => 'Vos réponses suggèrent un niveau de stress plutôt faible. Vos ressources de récupération semblent présentes : préservez les habitudes qui vous font du bien.',
            ],
            [
                'max_percentage' => 66,
                'level' => 'moderate',
                'feedback' => "Vos réponses suggèrent un niveau de stress modéré. Identifiez les sources de tension et accordez-vous des pauses sans écran, de l'activité physique et du sommeil régulier. Un échange avec un professionnel peut être utile si cela dure.",
            ],
            [
                'max_percentage' => 100,
                'level' => 'high',
                'feedback' => "Vos réponses suggèrent un niveau de stress élevé. Il est important de ne pas rester seul(e) face à cette charge : parlez-en à un professionnel de santé, à votre entourage ou à un service d'écoute, et réduisez ce qui peut l'être.",
            ],
        ],
        'wellbeing' => [
            [
                'max_percentage' => 33,
                'level' => 'low',
                'feedback' => "Vos réponses suggèrent un bien-être fragilisé en ce moment. Reprenez appui sur des gestes simples et sur une personne de confiance, et n'hésitez pas à demander de l'aide à un professionnel si vous vous sentez seul(e) ou dépassé(e).",
            ],
            [
                'max_percentage' => 66,
                'level' => 'moderate',
                'feedback' => "Vos réponses suggèrent un niveau de bien-être moyen. Certaines ressources sont présentes : cultivez les liens, le repos et les activités qui vous donnent de l'élan, sans vous mettre de pression.",
            ],
            [
                'max_percentage' => 100,
                'level' => 'high',
                'feedback' => 'Vos réponses suggèrent un bon niveau de bien-être. Continuez à entretenir ce qui vous soutient au quotidien et restez attentif(ve) aux signaux de fatigue.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Contacts d'urgence
    |--------------------------------------------------------------------------
    |
    | Le numéro Volkeno est centralisé ici afin de pouvoir être remplacé par
    | la ligne officielle validée par le partenaire sans toucher au seeder.
    |
    */
    'emergency_contacts' => [
        'volkeno_phone' => env('MINA_CARE_VOLKENO_PHONE', '+221 76 626 35 68'),
        'volkeno_email' => env('MINA_CARE_VOLKENO_EMAIL', 'contact@volkeno.sn'),
    ],
];
