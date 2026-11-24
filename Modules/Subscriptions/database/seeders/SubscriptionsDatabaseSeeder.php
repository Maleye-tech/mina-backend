<?php

namespace Modules\Subscriptions\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Subscriptions\Models\AccessCode;
use Modules\Subscriptions\Models\PartnerInstitution;
use Modules\Subscriptions\Models\SubscriptionPlan;

class SubscriptionsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $monthly = SubscriptionPlan::updateOrCreate(
            ['slug' => 'monthly'],
            [
                'name' => 'Abonnement Mensuel',
                'price' => 3000,
                'currency' => 'XOF',
                'duration_days' => 30,
                'description' => 'Accès illimité à tous les modules, QCM, cours et fiches pendant 30 jours.',
                'is_active' => true,
            ]
        );

        $yearly = SubscriptionPlan::updateOrCreate(
            ['slug' => 'yearly'],
            [
                'name' => 'Abonnement Annuel',
                'price' => 15000,
                'currency' => 'XOF',
                'duration_days' => 365,
                'description' => 'Accès illimité pendant un an complet, économisez plus de 50%.',
                'is_active' => true,
            ]
        );

        $crous = PartnerInstitution::updateOrCreate(
            ['code' => 'CROUS-DKR'],
            [
                'name' => 'CROUS Dakar - Université Cheikh Anta Diop',
                'type' => 'crous',
                'max_beneficiaries' => 1000,
                'current_beneficiaries' => 0,
                'subsidy_rate' => 100,
                'covers_mina_care' => true,
                'is_active' => true,
                'starts_at' => now(),
                'ends_at' => now()->addYear(),
            ]
        );

        AccessCode::updateOrCreate(
            ['code' => 'MINA-CROUS-DEMO'],
            [
                'partner_institution_id' => $crous->id,
                'plan_id' => $monthly->id,
                'duration_days' => 30,
                'is_used' => false,
                'expires_at' => now()->addMonths(6),
            ]
        );
    }
}
