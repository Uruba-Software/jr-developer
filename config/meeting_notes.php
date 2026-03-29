<?php

/**
 * T26 — Meeting Notes Configuration
 *
 * Cron expressions for scheduled meeting note delivery.
 * Override via environment variables for custom schedules.
 *
 * Format: standard 5-field cron (min hour dom mon dow)
 */

return [

    'cron' => [

        /*
         | Daily standup is hardcoded to weekdays at 09:00.
         | See routes/console.php.
         */

        /*
         | Sprint review: default = every Tuesday at 15:00
         | Override with MEETING_NOTES_CRON_SPRINT_REVIEW env var
         */
        'sprint_review' => env('MEETING_NOTES_CRON_SPRINT_REVIEW', '0 15 * * 2'),

        /*
         | Retrospective: default = every Tuesday at 15:30
         | Override with MEETING_NOTES_CRON_RETROSPECTIVE env var
         */
        'retrospective' => env('MEETING_NOTES_CRON_RETROSPECTIVE', '30 15 * * 2'),

    ],

];
