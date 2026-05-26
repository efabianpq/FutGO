<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('predictions:lock')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
