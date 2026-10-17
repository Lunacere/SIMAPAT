<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('meetings:send-reminders')->everyMinute()->withoutOverlapping();
