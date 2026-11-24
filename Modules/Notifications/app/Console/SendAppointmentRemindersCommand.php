<?php

namespace Modules\Notifications\Console;

use Illuminate\Console\Command;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Models\Appointment;
use Modules\Notifications\Notifications\AppointmentReminderNotification;

class SendAppointmentRemindersCommand extends Command
{
    protected $signature = 'mina:remind-appointments';

    protected $description = 'Envoie les rappels de rendez-vous prévus dans les 24 prochaines heures';

    public function handle(): int
    {
        $appointments = Appointment::query()
            ->where('status', AppointmentStatus::Confirmed->value)
            ->whereNull('reminder_sent_at')
            ->whereBetween('scheduled_at', [now(), now()->addHours(24)])
            ->with(['student', 'professional.user'])
            ->get();

        $count = 0;

        foreach ($appointments as $appointment) {
            if ($appointment->student) {
                $appointment->student->notify(new AppointmentReminderNotification($appointment));
                $appointment->update(['reminder_sent_at' => now()]);
                $count++;
            }
        }

        $this->info("{$count} rappels de consultation envoyés avec succès.");

        return Command::SUCCESS;
    }
}
