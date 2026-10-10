<?php

namespace App\Support;

use Carbon\CarbonInterface;

class SubmissionWindow
{
    public static function isOpen(?CarbonInterface $time = null): bool
    {
        $localTime = ($time ?? now())->copy()->setTimezone('Asia/Jakarta');
        $minutes = ($localTime->hour * 60) + $localTime->minute;

        return $minutes >= 7 * 60 && $minutes < 20 * 60;
    }
}
