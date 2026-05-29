<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('predictions:lock')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notifications:reminders')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/reminders.log'));

Schedule::command('results:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
