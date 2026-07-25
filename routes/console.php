<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('finances:refresh')->daily();
