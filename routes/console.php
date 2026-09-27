<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bookings:auto-cancel')->everyFiveMinutes();
