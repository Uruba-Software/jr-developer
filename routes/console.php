<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| T26 — Scheduled Meeting Note Delivery
|--------------------------------------------------------------------------
|
| Daily standup: every weekday at 09:00
| Sprint review: every other Tuesday at 15:00 (configurable via config)
| Retrospective: every other Tuesday at 15:30
|
*/

// Daily standup — weekdays at 09:00
Schedule::command('jr:standup')
    ->weekdays()
    ->at('09:00')
    ->name('jr-standup')
    ->withoutOverlapping();

// Sprint review — first Tuesday of every other week at 15:00
Schedule::command('jr:meeting-notes --type=sprint-review')
    ->cron(config('meeting_notes.cron.sprint_review', '0 15 * * 2'))
    ->name('jr-meeting-notes-sprint-review')
    ->withoutOverlapping();

// Retrospective — same cadence as sprint review, 30 min later
Schedule::command('jr:meeting-notes --type=retrospective')
    ->cron(config('meeting_notes.cron.retrospective', '30 15 * * 2'))
    ->name('jr-meeting-notes-retrospective')
    ->withoutOverlapping();
