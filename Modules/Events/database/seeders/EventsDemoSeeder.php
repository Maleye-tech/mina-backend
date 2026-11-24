<?php

namespace Modules\Events\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Events\Enums\EventType;
use Modules\Events\Models\Event;

class EventsDemoSeeder extends Seeder
{
    public function run(): void
    {
        Event::updateOrCreate(
            ['slug' => 'atelier-gestion-stress-examens'],
            [
                'title' => 'Atelier : Maîtriser le stress des examens et partiels',
                'type' => EventType::Workshop,
                'description' => 'Un atelier pratique de 2 heures en petit groupe pour acquérir des techniques de respiration, de cohérence cardiaque et d\'organisation des révisions.',
                'presenter_name' => 'Dr. Cheikh Ndiaye, Psychologue',
                'starts_at' => now()->addDays(7)->setHour(17)->setMinute(0),
                'ends_at' => now()->addDays(7)->setHour(19)->setMinute(0),
                'location_or_link' => 'https://meet.mina.sn/atelier-stress-partiels',
                'max_participants' => 30,
                'current_participants' => 0,
                'is_published' => true,
            ]
        );

        Event::updateOrCreate(
            ['slug' => 'webinaire-sommeil-et-reussite'],
            [
                'title' => 'Webinaire : Le sommeil, clé de la mémorisation et de la réussite',
                'type' => EventType::Webinar,
                'description' => 'Découvrez comment optimiser vos cycles de sommeil pendant les périodes de révision intensive pour décupler votre rétention d\'informations.',
                'presenter_name' => 'Fatou Fall, Neuroscientifique',
                'starts_at' => now()->addDays(14)->setHour(18)->setMinute(0),
                'ends_at' => now()->addDays(14)->setHour(19)->setMinute(30),
                'location_or_link' => 'https://meet.mina.sn/webinaire-sommeil-reussite',
                'max_participants' => 200,
                'current_participants' => 0,
                'is_published' => true,
            ]
        );

        Event::updateOrCreate(
            ['slug' => 'formation-ambassadeurs-care-session-1'],
            [
                'title' => 'Formation des Ambassadeurs Mina Care : Écoute active et repérage de crise',
                'type' => EventType::AmbassadorTraining,
                'description' => 'Formation certifiante destinée aux étudiants ambassadeurs pour apprendre les bases du soutien par les pairs, de l\'écoute bienveillante et du protocole d\'orientation d\'urgence.',
                'presenter_name' => 'Équipe Volkeno & Partenaires Santé',
                'starts_at' => now()->addDays(20)->setHour(10)->setMinute(0),
                'ends_at' => now()->addDays(20)->setHour(16)->setMinute(0),
                'location_or_link' => 'Centre Numérique CROUS UCAD / En ligne',
                'max_participants' => 50,
                'current_participants' => 0,
                'is_published' => true,
            ]
        );
    }
}
