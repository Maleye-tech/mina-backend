<?php

namespace Modules\Notifications\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Models\Appointment;
use Modules\Notifications\Notifications\AppointmentReminderNotification;

class SendAppointmentRemindersCommand extends Command
{
    protected $signature = 'mina:remind-appointments';

    protected $description = 'Envoie les rappels de rendez-vous prévus dans les 24 prochaines heures';

    public function handle(): int
    {
        $count = 0;

        Appointment::query()
            ->where('status', AppointmentStatus::Confirmed->value)
            ->whereNull('reminder_sent_at')
            ->whereBetween('scheduled_at', [now(), now()->addHours(24)])
            ->chunkById(100, function ($appointments) use (&$count): void {
                foreach ($appointments as $appointment) {
                    $sent = DB::transaction(function () use ($appointment): bool {
                        $appointment = Appointment::query()
                            ->whereKey($appointment->id)
                            ->whereNull('reminder_sent_at')
                            ->lockForUpdate()
                            ->first();

                        if (! $appointment) {
                            return false;
                        }

                        $appointment->student?->notify(new AppointmentReminderNotification($appointment));
                        $professional = $appointment->professionalUser ?? $appointment->professional?->user;
                        $professional?->notify(new AppointmentReminderNotification($appointment));
                        $appointment->update(['reminder_sent_at' => now()]);

                        return true;
                    });

                    $count += $sent ? 1 : 0;
                }
            });

        $this->info("{$count} rappels de consultation envoyés avec succès.");

        return Command::SUCCESS;
    }
}
