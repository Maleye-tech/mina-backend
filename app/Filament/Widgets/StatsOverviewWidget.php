<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Appointments\Models\Appointment;
use Modules\Assessment\Models\Attempt;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\Subscription;
use Modules\WellBeing\Models\UserTestResult;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalUsers = User::query()->count();
        $studentCount = User::query()->where('role', 'student')->count();

        $activeSubscriptions = Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->where('ends_at', '>', now())
            ->count();

        $activeTrials = Subscription::query()
            ->where('status', SubscriptionStatus::Trial->value)
            ->where('trial_ends_at', '>', now())
            ->count();

        $totalAppointments = Appointment::query()->count();
        $confirmedAppointments = Appointment::query()->where('status', 'confirmed')->count();

        $completedQcm = Attempt::query()->completed()->count();
        $completedWellbeingTests = UserTestResult::query()->count();
        $totalTests = $completedQcm + $completedWellbeingTests;

        return [
            Stat::make('Inscriptions', number_format($totalUsers))
                ->description("{$studentCount} étudiants inscrits")
                ->color('primary'),

            Stat::make('Abonnements actifs', number_format($activeSubscriptions))
                ->description("{$activeTrials} étudiants en essai gratuit")
                ->color('success'),

            Stat::make('Rendez-vous Mina Care', number_format($totalAppointments))
                ->description("{$confirmedAppointments} consultations confirmées")
                ->color('warning'),

            Stat::make('Tests & QCM passés', number_format($totalTests))
                ->description("{$completedQcm} sessions QCM, {$completedWellbeingTests} auto-évaluations")
                ->color('info'),
        ];
    }
}
