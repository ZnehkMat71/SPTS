<?php
// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
session_start();

// Set security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Add cache control headers to prevent back button access
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Include required files
require '../includes/connect.php';
require '../includes/calculate-hours.php';

// Session timeout handling (30 min)
$inactivity = 1800;
if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $inactivity) {
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    header("Location: ../index.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sip_supervisor' || !isset($_SESSION['sip_supervisor_logged_in'])) {
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    header("Location: ../index.php");
    exit();
}

// Session security
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Clear session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Send cache control headers
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    
    // Redirect to login page
    header("Location: ../index.php");
    exit();
}

$identifier = $_SESSION['identifier'] ?? null;

// Fetch SIP Supervisor info
$stmt = $conn->prepare("SELECT * FROM sip_supervisors WHERE username = ?"); 
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("s", $identifier);
$stmt->execute();
$supervisor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supervisor) {
    die("SIP Supervisor not found.");
}

// Calculate total hours and progress for the SIP center
list($total_hours, $average_progress, $total_students) = calculateSipCenterTotalHours($conn, $supervisor['sip_center']);

// Fetch assigned interns with their progress data, including school_year
$intern_stmt = $conn->prepare("SELECT student_id, fullname, school_year, course FROM students WHERE sip_center = ?");
if (!$intern_stmt) {
    die("Database error: " . $conn->error);
}
$intern_stmt->bind_param("s", $supervisor['sip_center']);
$intern_stmt->execute();
$interns_result = $intern_stmt->get_result();

$interns_data = [];

while ($intern = $interns_result->fetch_assoc()) {
    $progress_data = getStudentProgressData($conn, $intern['student_id']);
    
    $interns_data[] = [
        'student_id' => $intern['student_id'],
        'fullname' => $intern['fullname'],
        'course' => $intern['course'],
        'school_year' => $intern['school_year'], 
        'hours' => $progress_data['total_hours'],
        'progress' => $progress_data['progress_percentage'],
        'weekly_hours' => $progress_data['weekly_hours'],
        'monthly_hours' => $progress_data['monthly_hours']
    ];
}

$intern_stmt->close();
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SIP Center Supervisor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="../css/sip-supervisor-dashboard.css" rel="stylesheet">
    <!-- Add meta tags to prevent caching -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body>

<nav class="navbar navbar-expand-lg mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fas fa-chart-line"></i>
            SIP Center Supervisor Dashboard
        </a>
        <div class="ms-auto">
            <a class="btn btn-logout d-flex align-items-center gap-2" href="../index.php" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>
</nav>

<div class="content-wrapper">
    <div class="container">
        <div class="card welcome-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3>Welcome, <?= htmlspecialchars($supervisor['fullname']) ?></h3>
                    <p class="mb-0"><i class="fas fa-building me-2"></i> <?= htmlspecialchars($supervisor['sip_center']) ?></p>
                </div>
                <div class="avatar-initial" style="width: 60px; height: 60px; font-size: 1.5rem;">
                    <?= strtoupper(substr($supervisor['fullname'], 0, 1)) ?>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row text-center my-4">
            <div class="col-md-4">
                <div class="card summary-card">
                    <h5><i class="fas fa-users"></i>Total Interns</h5>
                    <p><?= $total_students ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card summary-card">
                    <h5><i class="fas fa-clock"></i>Total Hours Logged</h5>
                    <p><?= $total_hours ?> hrs</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card summary-card">
                    <h5><i class="fas fa-chart-pie"></i>Average Progress</h5>
                    <p><?= $average_progress ?>%</p>
                </div>
            </div>
        </div>

        <!-- Intern Table -->
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-list"></i>Assigned Student Interns</h4>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Course</th>
                            <th>School Year</th>
                            <th>Total Hours </th>
                            <th>Progress</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($interns_data as $intern):
                        $barColor = $intern['progress'] >= 100 ? 'bg-success' : ($intern['progress'] >= 50 ? 'bg-warning' : 'bg-info');
                        $initial = strtoupper(substr($intern['fullname'], 0, 1));
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($intern['student_id']) ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-initial"><?= $initial ?></div>
                                    <?= htmlspecialchars($intern['fullname']) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($intern['course']) ?></td> 
                            <td><?= htmlspecialchars($intern['school_year']) ?></td> 
                            
                            <td><?= $intern['hours'] ?> hrs</td>
                            <td>
                                <div class="progress">
                                    <div class="progress-bar <?= $barColor ?>" role="progressbar" style="width: <?= $intern['progress'] ?>%;">
                                        <?= $intern['progress'] ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="../views/daily-time-records.php?student_id=<?= urlencode($intern['student_id']) ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i>View Records
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<footer class="footer">
    © <?php echo date('Y'); ?> Student Progress Tracking System. All rights reserved.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

