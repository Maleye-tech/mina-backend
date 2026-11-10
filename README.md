# mina-backend

## Récupération du mot de passe

Le module Auth expose deux routes publiques pour récupérer un compte :

- `POST /api/auth/forgot-password` avec `{"email":"..."}` envoie un lien de réinitialisation. La réponse reste identique qu’un compte existe ou non.
- `POST /api/auth/reset-password` avec `email`, `token`, `password` et `password_confirmation` valide le lien et modifie le mot de passe. Les jetons Sanctum actifs du compte sont alors révoqués.

Le lien envoyé par courriel pointe vers `/reset-password` sur `FRONTEND_URL` et contient les paramètres `token` et `email`. Les deux routes sont limitées en fréquence.

## Vérification de l'adresse e-mail

L'inscription envoie un lien de vérification signé et limité dans le temps. `GET /api/auth/verify-email/{id}/{hash}` valide ce lien; l'utilisateur peut demander un nouvel envoi via `POST /api/auth/email/verification-notification` avec son jeton Sanctum. Le champ `email_verified` du profil indique si l'adresse a été confirmée.

## Pseudonyme, consentements et préférences

`PATCH /api/auth/settings` permet à tout utilisateur authentifié de modifier `pseudonym` (facultatif, unique), `preferred_language` (`fr` ou `en`) et `notifications_enabled` (préférence globale des notifications applicatives). L'endpoint accepte également les consentements explicites sous `consents.personal_data` et `consents.mental_health_data` (`true` pour accorder, `false` pour retirer). `GET /api/auth/me` et les réponses d'authentification exposent les préférences et l'état courant des consentements.

Chaque choix de consentement est ajouté à `user_consents` avec son type, sa valeur, sa version et son horodatage; un retrait ajoute une nouvelle entrée sans effacer l'historique. Une version modifiée rend les anciens accords invalides jusqu'à un nouvel accord. Le consentement données personnelles est exigé pour modifier ou terminer le profil étudiant; le consentement santé mentale est exigé pour les routes `WellBeing`. Après retrait ou changement de version, ces opérations répondent `403`; la gestion du compte et des consentements reste accessible.

Les versions sont configurées par `PERSONAL_DATA_CONSENT_VERSION` et `MENTAL_HEALTH_CONSENT_VERSION` (valeur par défaut `1.0`). L'interface cliente doit afficher les textes d'information approuvés et envoyer `true` uniquement après un choix explicite. Il reste à faire valider les textes par l’équipe juridique avant la mise en production. `notifications_enabled` est une préférence applicative; les courriels transactionnels d’authentification (vérification et récupération du mot de passe) ne sont pas désactivés par ce réglage.

## Profil étudiant et onboarding

Après connexion, un étudiant peut consulter `GET /api/auth/onboarding`, qui retourne les questions actives et les universités partenaires avec leurs facultés, puis envoyer ses réponses à `POST /api/auth/onboarding`. Les questions initiales portent sur `education_level` (niveau d'études), `study_field` (filière), `university_id` et `faculty_id`. Les questions configurées comme obligatoires doivent être renseignées pour terminer l'onboarding. `PATCH /api/auth/student-profile` permet ensuite de modifier partiellement le profil. Le profil de `GET /api/auth/me` expose les identifiants de référence et les noms de l'université et de la faculté sélectionnées.

Les universités et facultés initiales sont chargées par `php artisan db:seed` via `DatabaseSeeder`. Le référentiel inclut les huit établissements et les unités de santé transmis, répartis en public/privé; les URL non fournies comme sites officiels restent nulles.

Un admin peut consulter les définitions de toutes les questions avec `GET /api/auth/onboarding/questions` et modifier le libellé, l'ordre, l'état obligatoire ou actif via `PATCH /api/auth/onboarding/questions/{key}`. Les clés et types de questions restent fixes afin de préserver le format de profil attendu par l’application.

## Mot de passe et suppression de compte

- `PATCH /api/auth/password` exige `current_password`, `password` et `password_confirmation`. En cas de succès, les jetons Sanctum sont révoqués et l'utilisateur doit se reconnecter.
- `DELETE /api/auth/account` exige `current_password`, révoque les jetons Sanctum et supprime le compte.

Ces deux opérations nécessitent un jeton Sanctum valide et sont limitées en fréquence.

## Comptes gérés, rôles et sécurité admin

- Seul un admin authentifié peut appeler `POST /api/auth/managed-users` (alias de `POST /api/v1/users`), avec `name`, `email` et `role` (`ambassador` ou `professional`). Le nouvel utilisateur reçoit un lien pour définir son mot de passe ainsi qu’un lien de vérification d’adresse. La création publique reste réservée aux étudiants.
- Le module Users expose la ressource admin `GET /api/v1/users` (filtres `search`, `role`, `is_active`, `per_page`), `GET /api/v1/users/{id}`, `PATCH /api/v1/users/{id}` (`name`, `email`, `role`, `is_active`) et `DELETE /api/v1/users/{id}`. Un admin ne peut pas modifier son propre rôle, désactiver ou supprimer son propre compte, ni retirer le dernier admin actif; changer l’adresse e-mail réinitialise sa vérification et désactiver un compte révoque ses jetons.
- Le middleware `role` accepte les rôles définis par `UserRole`, par exemple `role:admin` ou `role:student,ambassador`. La ressource API de gestion des utilisateurs est réservée aux admins. Les contrôleurs des autres modules doivent appliquer le middleware correspondant à leur propre politique métier.
- Les routes API authentifiées exigent un jeton Sanctum de capacité `full-access`. `POST /api/auth/logout` révoque le jeton courant; `POST /api/auth/logout-all` révoque toutes les sessions. Le changement/réinitialisation du mot de passe et la suppression du compte révoquent aussi les jetons concernés.
- Les admins doivent activer TOTP. Après mot de passe, `POST /api/auth/login` fournit un `challenge_token` temporaire (10 min), sans accès normal à l’API. Pour le premier accès, appeler `POST /api/auth/two-factor/setup` puis `POST /api/auth/two-factor/confirm-setup` avec `code`; pour les connexions suivantes, appeler `POST /api/auth/two-factor/challenge` avec le code de l’application. La validation délivre le jeton API complet. Les codes TOTP déjà utilisés sont refusés.
- Le panneau Filament exige également une authentification TOTP pour les admins et partage le même secret, stocké chiffré. Les codes de récupération du panneau sont également chiffrés.

La migration MFA révoque les jetons des admins existants afin qu’ils repassent par l’enrôlement TOTP. Déployer le schéma avec `php artisan migrate`.