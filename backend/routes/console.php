<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SCRUM-613 - Rappel automatique 24h avant un rendez-vous.
Schedule::command('rendezvous:envoyer-rappels')->hourly();


// Security: apply the clinic-approved audit retention policy once per day.
// With AUDIT_RETENTION_DAYS=0 the command is a safe no-op.
Schedule::command('audit:prune')
    ->dailyAt('03:15')
    ->withoutOverlapping();
