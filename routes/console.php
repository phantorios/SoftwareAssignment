<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

//php artisan make:command ImportEquipment
//php artisan schedule:work
//php artisan equipment:parse

Schedule::command('equipment:parse')
    ->dailyAt('04:55')
    ->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
