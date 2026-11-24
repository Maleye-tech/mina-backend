<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Notifications\Console\CheckTrialExpirationsCommand;
use Modules\Notifications\Console\SendAppointmentRemindersCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SendAppointmentRemindersCommand::class)->hourly();
Schedule::command(CheckTrialExpirationsCommand::class)->daily();
