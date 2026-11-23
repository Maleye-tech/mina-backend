<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Mina API',
    description: 'API du backend Mina. Tous les endpoints sous /api. Authentification par jeton Sanctum (Bearer).',
)]
#[OA\Tag(name: 'Auth', description: 'Inscription, connexion, 2FA, mot de passe, onboarding')]
#[OA\Tag(name: 'Users', description: 'Gestion des utilisateurs et des rôles')]
#[OA\Tag(name: 'Assessment', description: 'QCM, flashcards et cas cliniques')]
#[OA\Tag(name: 'WellBeing', description: 'Articles, tests de bien-être et résultats')]
#[OA\Tag(name: 'Chat', description: 'Conversations et messages')]
#[OA\Tag(name: 'Coach', description: 'Coaching')]
#[OA\Tag(name: 'Appointments', description: 'Rendez-vous')]
#[OA\Tag(name: 'Library', description: 'Bibliothèque de ressources')]
#[OA\Tag(name: 'Progress', description: 'Suivi de progression')]
#[OA\Tag(name: 'Events', description: 'Événements')]
#[OA\Tag(name: 'Notifications', description: 'Notifications')]
#[OA\Tag(name: 'Payments', description: 'Paiements')]
#[OA\Tag(name: 'Subscriptions', description: 'Abonnements')]
#[OA\Tag(name: 'Dashboard', description: 'Tableau de bord')]
#[OA\Tag(name: 'Misc', description: 'Routes diverses')]
class OpenApi {}
