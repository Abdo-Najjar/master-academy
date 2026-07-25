<?php

use App\Jobs\CreateSystemBackupJob;
use Illuminate\Support\Facades\Schedule;

Schedule::command('finances:refresh')->daily();

Schedule::job(new CreateSystemBackupJob())->dailyAt('03:00');
Schedule::command('backup:clean')->dailyAt('03:30');
