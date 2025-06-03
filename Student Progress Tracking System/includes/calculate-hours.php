<?php
/**
 * Hours Calculator - Centralized functions for hour calculations across dashboards
 */

/**
 * Format time for display (e.g., "09:30 AM")
 * @param string $datetime MySQL datetime string
 * @return string Formatted time
 */
function formatTime($datetime) {
    return date('h:i A', strtotime($datetime));
}

/**
 * Format date for display (e.g., "Jan 15, 2024")
 * @param string $datetime MySQL datetime string
 * @return string Formatted date
 */
function formatDate($datetime) {
    return date('M d, Y', strtotime($datetime));
}

/**
 * Format date and time for display (e.g., "Jan 15, 2024 09:30 AM")
 * @param string $datetime MySQL datetime string
 * @return string Formatted date and time
 */
function formatDateTime($datetime) {
    return date('M d, Y h:i A', strtotime($datetime));
}

/**
 * Convert decimal hours to hours and minutes
 * @param float $decimal_hours Hours in decimal format
 * @return array [hours, minutes]
 */
function convertToHoursMinutes($decimal_hours) {
    $hours = floor($decimal_hours);
    $minutes = round(($decimal_hours - $hours) * 60);
    return [$hours, $minutes];
}

/**
 * Format hours and minutes for display (e.g., "2 hours 35 minutes")
 * @param float $hours Total hours in decimal
 * @return string Formatted hours string
 */
function formatHours($hours) {
    list($whole_hours, $minutes) = convertToHoursMinutes($hours);
    return sprintf('%d hours %d minutes', $whole_hours, $minutes);
}

/**
 * Format hours for display with decimal (e.g., "2.58 hours")
 * @param float $hours Total hours in decimal
 * @return string Formatted hours string
 */
function formatHoursDecimal($hours) {
    list($whole_hours, $minutes) = convertToHoursMinutes($hours);
    return sprintf('%d hours %d minutes', $whole_hours, $minutes);
}

/**
 * Calculate total hours from seconds
 * @param int $seconds Total seconds
 * @return array Array containing hours and minutes
 */
function calculateHoursFromSeconds($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    return [
        'hours' => $hours,
        'minutes' => $minutes,
        'formatted' => sprintf('%02d:%02d', $hours, $minutes)
    ];
}

/**
 * Calculate progress percentage based on total hours
 * @param int $seconds Total seconds worked
 * @param int $target_hours Target hours (default 300)
 * @return int Progress percentage (0-100)
 */
function calculateProgressPercentage($seconds, $target_hours = 300) {
    return min(100, floor(($seconds / ($target_hours * 3600)) * 100));
}

/**
 * Calculate total hours for a student
 * @param mysqli $conn Database connection
 * @param string $student_id Student identifier
 * @return array [total_hours, progress_percentage]
 */
function calculateStudentTotalHours($conn, $student_id) {
    $stmt = $conn->prepare("
        SELECT SUM(TIMESTAMPDIFF(SECOND, clock_in, clock_out)) / 3600 AS total_hours
        FROM time_tracking
        WHERE student_id = ? 
        AND clock_out IS NOT NULL 
        AND clock_out > clock_in
    ");
    
    if (!$stmt) {
        return [0, 0];
    }
    
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $stmt->bind_result($total_hours);
    $stmt->fetch();
    $stmt->close();
    
    $total_hours = round($total_hours ?? 0, 2);
    $progress_percentage = min(100, round(($total_hours / 300) * 100));
    
    return [$total_hours, $progress_percentage];
}

/**
 * Calculate total hours for all students in a SIP center
 * @param mysqli $conn Database connection
 * @param string $sip_center SIP center name
 * @return array [total_hours, average_progress, total_students]
 */
function calculateSipCenterTotalHours($conn, $sip_center) {
    $stmt = $conn->prepare("
        SELECT s.student_id, s.fullname
        FROM students s
        WHERE s.sip_center = ?
    ");
    
    if (!$stmt) {
        return [0, 0, 0];
    }
    
    $stmt->bind_param("s", $sip_center);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_students = $result->num_rows;
    
    $total_hours = 0;
    $total_progress = 0;
    
    while ($student = $result->fetch_assoc()) {
        list($hours, $progress) = calculateStudentTotalHours($conn, $student['student_id']);
        $total_hours += $hours;
        $total_progress += $progress;
    }
    
    $stmt->close();
    
    $average_progress = $total_students > 0 ? round($total_progress / $total_students) : 0;
    
    return [$total_hours, $average_progress, $total_students];
}

/**
 * Calculate hours for a specific date range
 * @param mysqli $conn Database connection
 * @param string $student_id Student identifier
 * @param string $start_date Start date (YYYY-MM-DD)
 * @param string $end_date End date (YYYY-MM-DD)
 * @return array [total_hours, entries]
 */
function calculateDateRangeHours($conn, $student_id, $start_date, $end_date) {
    $stmt = $conn->prepare("
        SELECT 
            DATE(clock_in) as date,
            clock_in,
            clock_out,
            TIMESTAMPDIFF(SECOND, clock_in, clock_out) / 3600 as hours
        FROM time_tracking
        WHERE student_id = ?
        AND DATE(clock_in) BETWEEN ? AND ?
        AND clock_out IS NOT NULL
        AND clock_out > clock_in
        ORDER BY clock_in DESC
    ");
    
    if (!$stmt) {
        return [0, []];
    }
    
    $stmt->bind_param("sss", $student_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $total_hours = 0;
    $entries = [];
    
    while ($row = $result->fetch_assoc()) {
        $hours = round($row['hours'], 2);
        $total_hours += $hours;
        list($whole_hours, $minutes) = convertToHoursMinutes($hours);
        
        $entries[] = [
            'date' => formatDate($row['date']),
            'clock_in' => formatTime($row['clock_in']),
            'clock_out' => formatTime($row['clock_out']),
            'hours' => sprintf('%d hours %d minutes', $whole_hours, $minutes),
            'hours_decimal' => $hours
        ];
    }
    
    $stmt->close();
    
    return [$total_hours, $entries];
}

/**
 * Calculate weekly hours for a student
 * @param mysqli $conn Database connection
 * @param string $student_id Student identifier
 * @return array [total_hours, entries]
 */
function calculateWeeklyHours($conn, $student_id) {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d');
    return calculateDateRangeHours($conn, $student_id, $start_date, $end_date);
}

/**
 * Calculate monthly hours for a student
 * @param mysqli $conn Database connection
 * @param string $student_id Student identifier
 * @return array [total_hours, entries]
 */
function calculateMonthlyHours($conn, $student_id) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
    return calculateDateRangeHours($conn, $student_id, $start_date, $end_date);
}

/**
 * Calculate daily hours for a student
 * @param mysqli $conn Database connection
 * @param string $student_id Student identifier
 * @return array Array containing daily sessions with accumulated hours
 */
function calculateDailyHours($conn, $student_id) {
    $stmt = $conn->prepare("
        SELECT 
            DATE(clock_in) as date,
            clock_in,
            clock_out
        FROM time_tracking 
        WHERE student_id = ? 
        ORDER BY clock_in DESC
    ");
    
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $daily_sessions = [];
    $daily_hours = [];
    
    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        $clock_in = strtotime($row['clock_in']);
        $duration = 0;
        
        if (!empty($row['clock_out'])) {
            $clock_out = strtotime($row['clock_out']);
            $duration = max(0, $clock_out - $clock_in);
        }
        
        $daily_hours[$date] = ($daily_hours[$date] ?? 0) + $duration;
        list($whole_hours, $minutes) = convertToHoursMinutes($duration / 3600);
        
        $daily_sessions[] = [
            'date' => formatDate($date),
            'login_time' => formatTime($row['clock_in']),
            'logout_time' => !empty($row['clock_out']) ? formatTime($row['clock_out']) : 'N/A',
            'duration' => sprintf('%d hours %d minutes', $whole_hours, $minutes),
            'duration_decimal' => $duration / 3600
        ];
    }
    
    $stmt->close();
    return $daily_sessions;
}

/**
 * Get student progress data
 * @param mysqli $conn Database connection
 * @param string $student_id Student identifier
 * @return array [total_hours, progress_percentage, weekly_hours, monthly_hours]
 */
function getStudentProgressData($conn, $student_id) {
    list($total_hours, $progress_percentage) = calculateStudentTotalHours($conn, $student_id);
    list($weekly_hours) = calculateWeeklyHours($conn, $student_id);
    list($monthly_hours) = calculateMonthlyHours($conn, $student_id);
    
    list($total_whole_hours, $total_minutes) = convertToHoursMinutes($total_hours);
    list($weekly_whole_hours, $weekly_minutes) = convertToHoursMinutes($weekly_hours);
    list($monthly_whole_hours, $monthly_minutes) = convertToHoursMinutes($monthly_hours);
    
    return [
        'total_hours' => $total_hours,
        'total_hours_formatted' => sprintf('%d hours %d minutes', $total_whole_hours, $total_minutes),
        'progress_percentage' => $progress_percentage,
        'weekly_hours' => $weekly_hours,
        'weekly_hours_formatted' => sprintf('%d hours %d minutes', $weekly_whole_hours, $weekly_minutes),
        'monthly_hours' => $monthly_hours,
        'monthly_hours_formatted' => sprintf('%d hours %d minutes', $monthly_whole_hours, $monthly_minutes)
    ];
} 