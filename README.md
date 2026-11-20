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
- Les admins reçoivent un code de connexion par e-mail. Après le mot de passe, `POST /api/auth/login` renvoie un `challenge_token` temporaire (15 min, sans accès à l’API) et envoie un code à 6 chiffres à l’adresse du compte (valable 10 minutes, 5 tentatives maximum). `POST /api/auth/two-factor/challenge` avec ce code délivre le jeton API complet; le code et le jeton de challenge sont invalidés après usage. Les routes `two-factor/setup` et `two-factor/confirm-setup` ont été retirées.
- Le panneau Filament conserve sa propre authentification TOTP pour les admins, avec secret stocké chiffré et codes de récupération chiffrés; ce secret n’intervient plus dans l’ouverture de session API.

La migration MFA révoque les jetons des admins existants afin qu’ils repassent par l’enrôlement TOTP. Déployer le schéma avec `php artisan migrate`.

## Envoi d’e-mails (Brevo)

Les e-mails transactionnels — vérification d’adresse, réinitialisation de mot de passe et code de connexion admin — partent via l’API transactionnelle de Brevo (`api.brevo.com/v3/smtp/email`). `MAIL_MAILER=brevo` active le transport maison `App\Mail\BrevoTransport`, enregistré dans `AppServiceProvider`, qui s’authentifie avec `BREVO_API_KEY`; l’expéditeur vient de `BREVO_FROM_EMAIL`/`BREVO_FROM_NAME` (source de `MAIL_FROM_ADDRESS` et `MAIL_FROM_NAME`). L’alternative SMTP (`smtp-relay.brevo.com`, port 587) exige une SMTP key distincte de l’API key, générée dans Brevo (Settings → SMTP & API). Les tests utilisent le transport `array` (`phpunit.xml`) et n’envoient rien.

## Bibliothèque (Library)

Le module Library gère le contenu pédagogique : matières (`subjects`), niveaux (`levels`), contenus rédactionnels (`contents` : `course`, `summary_sheet`, `recap`) et podcasts. Toutes les routes ci-dessous sont sous `/api/v1/library`, exigent un jeton `full-access` et ne retourne que les éléments publiés (`is_published = true`).

- `GET /api/v1/library/subjects` et `GET /api/v1/library/levels` retournent les taxonomies triées (`sort_order`, puis `name`).
- `GET /api/v1/library/contents` liste les contenus avec pagination (15 par page, `per_page` jusqu'à 100). Filtres : `search` (titre/description), `subject_id`, `level_id`, `type` (`course` | `summary_sheet` | `recap`), `favorite=1` (favoris de l'utilisateur courant). Chaque item expose `favorited` calculé pour l'utilisateur.
- `GET /api/v1/library/contents/{id}` retourne le contenu complet avec `body` (HTML riche) et l'état `favorited`. Un contenu non publié répond `404`.
- `POST /api/v1/library/contents/{id}/favorite` et `DELETE .../favorite` gèrent le favori de l'utilisateur (liste polymorphe `library_favorites`, partagée avec les podcasts).
- `GET /api/v1/library/podcasts` accepte les mêmes filtres que les contenus (sans `type`). `GET /api/v1/library/podcasts/{id}`, `POST .../favorite` et `DELETE .../favorite` suivent la même logique.
- `POST /api/v1/library/podcasts` (admin uniquement, limitée à 10 requêtes/minute) accepte un multipart avec `title` (requis), `audio` (fichier ≤ 200 Mo, mp3/wav/m4a/aac/ogg), `description`, `subject_id`, `level_id`, `is_published` et `sort_order`. L'audio est stocké sur `LIBRARY_AUDIO_DISK` (S3 en production) ; la création déclenche en file la compression `CompressPodcastAudio` (ffmpeg, bitrate configurable via `config/library.php`, repli `skipped` si ffmpeg est absent). `compression_status` suit `pending → processing → done | failed | skipped`.
- `GET /api/v1/library/offline-manifest` retourne le manifeste de synchronisation hors-ligne : taxonomies, tous les contenus publiés (avec `body`), les podcasts favorisés publiés (avec `audio_url` signé à durée limitée, `signed_url_ttl_minutes`) et les identifiants de favoris.

Les ressources Filament « Bibliothèque » (Niveaux, Matières, Contenus, Podcasts) permettent aux admins de gérer taxonomies, rédaction HTML et publications depuis le panneau, avec upload audio pour les podcasts. Le panneau et l'API partagent les mêmes tables `library_*`.

## Évaluation (Assessment)

Le module Assessment couvre les QCM, les cartes mémoire à répétition espacée (SM-2) et les cas cliniques. Toutes les routes sont sous `/api/v1`, exigent un jeton `full-access`, et le catalogue ne retourne que les contenus publiés.

- **QCM** : `GET /api/v1/qcm/questions` (filtres `subject_id`, `level_id`, `difficulty`, `per_page`) et `GET /api/v1/qcm/questions/{id}` — le catalogue n'expose ni `is_correct` ni `explanation` avant réponse. Sessions : `GET/POST /api/v1/qcm/sessions` (`subject_id`, `level_id`, `difficulty`, `question_count` ≤ 50), `GET /api/v1/qcm/sessions/{id}`, `POST .../answers` (`question_id`, `option_ids`, 60/min) renvoie la correction complète, `POST .../finish` clôture et calcule le score.
- **Flashcards** : `GET /api/v1/flashcards/decks`, `GET .../decks/{id}`, `GET /api/v1/flashcards/reviews/today`, `POST /api/v1/flashcards/{id}/reviews` avec `quality` de 0 (`blackout`) à 5 (`perfect`), 60/min.
- **Cas cliniques** : `GET /api/v1/clinical-cases`, `GET .../{id}` ; tentatives `GET/POST /api/v1/clinical-cases/attempts` (`clinical_case_id`), `GET .../attempts/{id}`, `POST .../answers` (`step_id`, `answer`, 60/min) avec corrigé révélé progressivement, `POST .../finish`.
- **Imports** (admin, 10/min) : `GET/POST /api/v1/qcm/imports`, `GET .../{id}` — `POST` accepte `file` et `dry_run` et pousse le job `ProcessQcmImport`.

Les ressources Filament « Évaluation » (Questions, Paquets de flashcards, Cas cliniques, Imports QCM) permettent la gestion complète des contenus depuis le panneau admin.

### Import de QCM

L'import accepte un fichier **CSV ou Excel `.xlsx`** (première feuille, 2 Mo maximum), via l'API `POST /api/v1/qcm/imports` ou la ressource Filament « Imports QCM ». En-tête attendu (normalisé en minuscules, séparateurs convertis en `_`) :

- obligatoires : `subject`, `level`, `question`, `correct` ;
- options : `option_a` à `option_e` (2 à 5 renseignées) ;
- facultatifs : `difficulty` (`easy` | `medium` | `hard`, défaut `medium`), `question_type` (`single` | `multiple`, déduit de `correct` si absent), `status` (`draft` | `published`, défaut `draft`), `explanation`, `source_reference`.

La colonne `correct` accepte les lettres A à E ou les chiffres 1 à 5, séparés par virgules, espaces ou points-virgules pour une réponse multiple. Les doublons (matière + niveau + énoncé normalisé) sont comptés, y compris parmi les questions supprimées ; `dry_run=true` valide et compte sans écrire. Les erreurs ligne à ligne (500 maximum) sont stockées dans `errors` ; un format non supporté passe l'import en échec.

## Mina Care (WellBeing)

Le module WellBeing gère les fiches de prévention, les tests d'auto-évaluation, l'historique personnel et les contacts d'urgence. Toutes les routes sont sous `/api/v1/wellbeing`, exigent un jeton `full-access` **et le consentement courant aux données de santé mentale** (`consents.mental_health_data`, accordé via `PATCH /api/auth/settings`) ; sans consentement, elles répondent `403`.

- `GET /api/v1/wellbeing/articles` et `GET .../articles/{slug}` : fiches de prévention publiées.
- `GET /api/v1/wellbeing/tests` et `GET .../tests/{id}` : catalogue de tests d'auto-évaluation. `POST .../tests/{id}/results` (30/min) soumet `answers: [{question_id, option_key}]` et recalcule les scores.
- `GET /api/v1/wellbeing/results` et `GET .../results/{id}` : historique personnel des résultats.
- `GET /api/v1/wellbeing/emergency-contacts` : contacts d'urgence actifs.
- `GET /api/v1/wellbeings` : route de compatibilité renvoyant le catalogue des fiches.

Les ressources Filament du module (fiches, tests, résultats, contacts d'urgence) vivent dans `Modules/WellBeing/app/Filament/Resources` et apparaissent dans le panneau admin sous « Mina Care ».