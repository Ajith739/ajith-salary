<?php
/**
 * Indian Calendar — Working Day Calculations
 * 
 * Rules:
 * - Every Sunday is a holiday
 * - 2nd Saturday is a holiday
 * - 4th Saturday is a holiday
 * - All other days (Mon-Fri + 1st, 3rd, 5th Sat) are working days
 */

/**
 * Calculate working days for a given month
 * 
 * @param int $year  Full year (e.g. 2026)
 * @param int $month Month number (1-12)
 * @param array $settings User settings for holiday rules
 * @return int Number of working days
 */
function getWorkingDays(int $year, int $month, array $settings = []): int {
    $sundayHoliday = $settings['sunday_holiday'] ?? 1;
    $secondSatHoliday = $settings['second_saturday_holiday'] ?? 1;
    $fourthSatHoliday = $settings['fourth_saturday_holiday'] ?? 1;
    
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $workingDays = 0;
    
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = mktime(0, 0, 0, $month, $day, $year);
        $dayOfWeek = (int)date('w', $date); // 0=Sunday, 6=Saturday
        
        $isHoliday = false;
        
        // Sunday check
        if ($dayOfWeek === 0 && $sundayHoliday) {
            $isHoliday = true;
        }
        
        // Saturday checks
        if ($dayOfWeek === 6) {
            $saturdayNumber = getSaturdayNumber($day);
            if ($saturdayNumber === 2 && $secondSatHoliday) {
                $isHoliday = true;
            }
            if ($saturdayNumber === 4 && $fourthSatHoliday) {
                $isHoliday = true;
            }
        }
        
        if (!$isHoliday) {
            $workingDays++;
        }
    }
    
    return $workingDays;
}

/**
 * Determine which Saturday of the month a given day falls on
 * (Only call this when the day IS a Saturday)
 */
function getSaturdayNumber(int $dayOfMonth): int {
    // First Saturday can be day 1-7, second 8-14, third 15-21, fourth 22-28, fifth 29-31
    return (int)ceil($dayOfMonth / 7);
}

/**
 * Get all holiday dates for a month
 * Returns array of ['date' => 'Y-m-d', 'type' => 'sunday|2nd_saturday|4th_saturday']
 */
function getHolidayDates(int $year, int $month, array $settings = []): array {
    $sundayHoliday = $settings['sunday_holiday'] ?? 1;
    $secondSatHoliday = $settings['second_saturday_holiday'] ?? 1;
    $fourthSatHoliday = $settings['fourth_saturday_holiday'] ?? 1;
    
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $holidays = [];
    
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = mktime(0, 0, 0, $month, $day, $year);
        $dayOfWeek = (int)date('w', $date);
        $dateStr = date('Y-m-d', $date);
        
        if ($dayOfWeek === 0 && $sundayHoliday) {
            $holidays[] = ['date' => $dateStr, 'day' => $day, 'type' => 'sunday', 'label' => 'Sunday'];
        }
        
        if ($dayOfWeek === 6) {
            $satNum = getSaturdayNumber($day);
            if ($satNum === 2 && $secondSatHoliday) {
                $holidays[] = ['date' => $dateStr, 'day' => $day, 'type' => '2nd_saturday', 'label' => '2nd Saturday'];
            }
            if ($satNum === 4 && $fourthSatHoliday) {
                $holidays[] = ['date' => $dateStr, 'day' => $day, 'type' => '4th_saturday', 'label' => '4th Saturday'];
            }
        }
    }
    
    return $holidays;
}

/**
 * Get detailed calendar data for a month
 * Each day: date, dayOfWeek, isHoliday, holidayType, isWorkingDay, isToday
 */
function getCalendarData(int $year, int $month, array $settings = []): array {
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $today = date('Y-m-d');
    $calendar = [];
    
    // First day of month - what day of week?
    $firstDayOfWeek = (int)date('w', mktime(0, 0, 0, $month, 1, $year));
    
    $sundayHoliday = $settings['sunday_holiday'] ?? 1;
    $secondSatHoliday = $settings['second_saturday_holiday'] ?? 1;
    $fourthSatHoliday = $settings['fourth_saturday_holiday'] ?? 1;
    
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = mktime(0, 0, 0, $month, $day, $year);
        $dayOfWeek = (int)date('w', $date);
        $dateStr = date('Y-m-d', $date);
        
        $isHoliday = false;
        $holidayType = '';
        
        if ($dayOfWeek === 0 && $sundayHoliday) {
            $isHoliday = true;
            $holidayType = 'Sunday';
        }
        
        if ($dayOfWeek === 6) {
            $satNum = getSaturdayNumber($day);
            if ($satNum === 2 && $secondSatHoliday) {
                $isHoliday = true;
                $holidayType = '2nd Saturday';
            }
            if ($satNum === 4 && $fourthSatHoliday) {
                $isHoliday = true;
                $holidayType = '4th Saturday';
            }
        }
        
        $calendar[] = [
            'date' => $dateStr,
            'day' => $day,
            'dayOfWeek' => $dayOfWeek,
            'dayName' => date('l', $date),
            'shortDayName' => date('D', $date),
            'isHoliday' => $isHoliday,
            'holidayType' => $holidayType,
            'isWorkingDay' => !$isHoliday,
            'isToday' => ($dateStr === $today),
            'isSaturday' => ($dayOfWeek === 6),
            'isSunday' => ($dayOfWeek === 0),
        ];
    }
    
    return [
        'year' => $year,
        'month' => $month,
        'monthName' => date('F', mktime(0, 0, 0, $month, 1, $year)),
        'daysInMonth' => $daysInMonth,
        'firstDayOfWeek' => $firstDayOfWeek,
        'days' => $calendar
    ];
}