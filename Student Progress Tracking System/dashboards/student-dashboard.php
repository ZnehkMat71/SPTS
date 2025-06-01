<?php
date_default_timezone_set('Asia/Manila');
ob_start();
session_start();

// Set security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

require '../includes/connect.php';
require '../includes/calculate-hours.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Check login session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student' || !isset($_SESSION['student_logged_in'])) {
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

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    header("Location: ../index.php");
    exit();
}

// Set MySQL timezone
$conn->query("SET time_zone = '+08:00'");
$user_id = $_SESSION['user_id'];

// Error handler
function handle_error($message) {
    if (!isset($_SESSION['has_redirected'])) {
        $_SESSION['error'] = $message;
        $_SESSION['has_redirected'] = true;
        header("Location: student-dashboard.php");
        exit();
    } else {
        echo "<p style='color:red; text-align:center; font-weight:bold;'>$message</p>";
        exit();
    }
}

// Query helper
function execute_query($conn, $query, $params = [], $types = "") {
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
        return $stmt;
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        handle_error("A database error occurred. Please try again later.");
        return false;
    }
}

// Fetch student_id
$stmt = execute_query($conn, "SELECT identifier FROM users WHERE id = ? AND role = 'student'", [$user_id], "i");
$stmt->bind_result($student_id);
$stmt->fetch();
$stmt->close();

if (empty($student_id)) handle_error("Invalid student account.");

// Get student profile and required hours from supervisor
$stmt = execute_query($conn, "
    SELECT 
        s.fullname, 
        s.is_approved, 
        s.school_year,
        s.course,
        sv.required_hours
    FROM students s
    LEFT JOIN supervisors sv ON s.supervisor = sv.id
    WHERE s.student_id = ?
", [$student_id], "s");

$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    error_log("Student profile not found for student_id: " . $student_id);
    handle_error("Student profile not found. Please contact your administrator.");
}

$student_name = htmlspecialchars($student['fullname']);
$is_approved = (bool) $student['is_approved'];
$required_hours = (int) ($student['required_hours'] ?? 300);
$school_year = htmlspecialchars($student['school_year'] ?? 'N/A');
$course = htmlspecialchars($student['course'] ?? 'N/A');

// Check today's time log
$stmt = execute_query($conn, "
    SELECT clock_in, clock_out 
    FROM time_tracking 
    WHERE student_id = ? 
    AND DATE(clock_in) = CURDATE()
    ORDER BY clock_in DESC 
    LIMIT 1
", [$student_id], "s");

$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

$has_open_entry = $result && is_null($result['clock_out']);
$already_logged_today = $result && !is_null($result['clock_out']);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        handle_error("Security validation failed.");
    }

    if (!$is_approved) {
        handle_error("Account not approved for time tracking.");
    }

    // TIME IN
    if (isset($_POST['time_in'])) {
        if ($already_logged_today) handle_error("You've already completed a time entry today.");
        if ($has_open_entry) handle_error("You have an open time entry. Please clock out first.");

        $stmt = execute_query($conn, "INSERT INTO time_tracking (student_id, clock_in) VALUES (?, NOW())", [$student_id], "s");
        $success = $stmt->affected_rows > 0;
        $stmt->close();

        $msg = $success 
            ? "Clocked in successfully at " . date("g:i A")
            : "Error clocking in: " . $conn->error;
        redirect_dashboard($success ? 'success' : 'error', $msg);
    }

    // TIME OUT
    if (isset($_POST['time_out'])) {
        if (!$has_open_entry) handle_error("No open time entry to clock out.");
        if ($already_logged_today) handle_error("You've already completed a time entry today.");

        $stmt = execute_query($conn, "
            UPDATE time_tracking 
            SET clock_out = NOW() 
            WHERE student_id = ? 
            AND clock_out IS NULL 
            AND DATE(clock_in) = CURDATE()
            LIMIT 1
        ", [$student_id], "s");

        $success = $stmt->affected_rows > 0;
        $stmt->close();

        $msg = $success 
            ? "Clocked out successfully at " . date("g:i A")
            : "Error clocking out: " . $conn->error;
        redirect_dashboard($success ? 'success' : 'error', $msg);
    }
}

// Calculate confirmed hours
$stmt = execute_query($conn, 
    "SELECT SUM(TIMESTAMPDIFF(SECOND, clock_in, clock_out)) AS total_seconds
     FROM time_tracking
     WHERE student_id = ? AND clock_out IS NOT NULL AND confirmation_status = 1",
    [$student_id], 
    "s"
);

$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_seconds = (int) ($result['total_seconds'] ?? 0);
$hours = floor($total_seconds / 3600);
$minutes = floor(($total_seconds % 3600) / 60);
$total_hours = sprintf('%d hours %d minutes', $hours, $minutes);

// Progress
$progress_percentage = $required_hours > 0 
    ? min(100, round(($total_seconds / ($required_hours * 3600)) * 100)) 
    : 0;

// Weekly logs
$stmt = execute_query($conn, "
    SELECT 
        DATE_FORMAT(clock_in, '%b %d, %Y') AS log_date,
        DATE_FORMAT(clock_in, '%h:%i %p') AS time_in,
        IFNULL(DATE_FORMAT(clock_out, '%h:%i %p'), 'Pending') AS time_out,
        confirmation_status
    FROM time_tracking
    WHERE student_id = ?
    AND YEARWEEK(clock_in, 1) = YEARWEEK(CURDATE(), 1)
    ORDER BY clock_in DESC",
    [$student_id], 
    "s"
);

$recent_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Checked documents
$stmt = execute_query($conn, "
    SELECT document_name 
    FROM document_checklist 
    WHERE student_identifier = ? AND is_checked = 1
    ORDER BY checked_at DESC
", [$student_id], "s");

$submitted_docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$server_timestamp = time();
unset($_SESSION['has_redirected']);
ob_end_flush();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Student Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet"/>
  <link href="../css/student-dashboard.css" rel="stylesheet">
</head>
<body>
  <div class="header">
    <div class="logo">
      <i class="fas fa-chart-line"></i>
      Student Progress
    </div>
    <div class="nav-links">
      <a href="?logout=true" class="btn">Logout</a>
    </div>
  </div>
  <div class="dashboard">
    <div class="welcome-section">
        <div class="profile-left">
            <div class="profile-header">
                 <i class="fas fa-user"></i> <!-- User icon -->
                <h1><?= $student_name ?></h1>
            </div>
            <div class="profile-details">
                <span class="detail-item">
                    <i class="fas fa-id-card"></i>
                    <?= htmlspecialchars($student_id) ?>
                </span>
                <span class="detail-item">
                    <i class="fas fa-graduation-cap"></i>
                    <?= $course ?>
                </span>
                <span class="detail-item">
                    <i class="fas fa-calendar"></i>
                    <?= $school_year ?>
                </span>
            </div>
            <div class="profile-status">
                <span class="status-badge <?= $is_approved ? 'status-approved' : 'status-pending' ?>">
                    <i class="fas fa-<?= $is_approved ? 'check-circle' : 'clock' ?>"></i>
                    <?= $is_approved ? 'Approved' : 'Pending Approval' ?>
                </span>
            </div>
        </div>
        <div class="progress-indicator">
            <h3><?= $progress_percentage ?>% of required hours completed</h3>
        </div>
    </div>
        <?php if (isset($_SESSION['success'])): ?>
    <div class="approval-warning approval-success">
        <i class="fas fa-check-circle"></i>
        <p><?= $_SESSION['success']; unset($_SESSION['success']); ?></p>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="approval-warning error">
        <i class="fas fa-exclamation-circle"></i>
        <p><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
    </div>
    <?php endif; ?>

    <?php if (!$is_approved): ?>
    <div class="approval-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <p>Your account is pending supervisor approval. Time tracking features will be disabled until your account is approved.</p>
    </div>
    <?php else: ?>
    <div class="approval-warning approval-success">
        <i class="fas fa-check-circle"></i>
        <p>Your account has been approved by the supervisor. You can now use all time tracking features.</p>
    </div>
    <?php endif; ?>

    <div class="main-content">
        <div class="time-tracking">
            <h2>Time Tracking</h2>
            <div class="clock-display" id="liveClock"></div>
            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="button-group">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit" name="time_in" class="btn time-in" <?= ($has_open_entry || !$is_approved || $already_logged_today) ? 'disabled' : '' ?>>
                    <i class="fas fa-play"></i> Time In
                </button>
                <button type="submit" name="time_out" class="btn time-out" <?= (!$has_open_entry || !$is_approved || $already_logged_today) ? 'disabled' : '' ?>>
                    <i class="fas fa-stop"></i> Time Out
                </button>
            </form>
        </div>

        <div class="tasks-list">
            <div class="tasks-header">
                <h2>Recent Activity</h2>
                <div class="filter-btn">
                    <i class="fas fa-calendar"></i>
                    This Week
                </div>
            </div>
            <?php if (!empty($recent_logs)): ?>
                <?php foreach ($recent_logs as $row): ?>
                    <div class="task-item">
                        <div>
                            <h4><?= htmlspecialchars($row['log_date']) ?></h4>
                            <p>Time In: <?= htmlspecialchars($row['time_in']) ?></p>
                            <p>Time Out: <?= htmlspecialchars($row['time_out']) ?></p>
                        </div>
                        <span class="status-badge <?= $row['time_out'] === 'Pending' ? 'status-in-progress' : 'status-completed' ?>">
                            <?= $row['time_out'] === 'Pending' ? 'In Progress' : 'Completed' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-tasks">No recent activity</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="progress-documents-section">
        <!-- Progress Section -->
        <div class="progress-section">
            <h2>Overall Progress</h2>
            <?php if (!$is_approved): ?>
            <div class="approval-warning" style="margin-top: 0;">
                <i class="fas fa-lock"></i>
                <p>Progress tracking will be available after supervisor approval.</p>
            </div>
            <?php endif; ?>
            
            <div class="progress-stats">
                <div class="stat-group">
                    <span class="stat-label">Total Hours</span>
                    <span class="stat-value"><?= htmlspecialchars($total_hours) ?></span>
                </div>
                <div class="stat-group">
                    <span class="stat-label">Goal</span>
                    <span class="stat-value"><?= $required_hours ?> hours</span>
                </div>
                <div class="stat-group">
                    <span class="stat-label">Progress</span>
                    <span class="stat-value"><?= $progress_percentage ?>%</span>
                </div>
            </div>
            
            <div class="progress-bar-container">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $progress_percentage ?>%"></div>
                </div>
            </div>

            <a href="../views/daily-time-records.php" class="view-records-btn" <?= !$is_approved ? 'style="pointer-events: none; opacity: 0.6;"' : '' ?>>
                <i class="fas fa-clock"></i>
                View Daily Time Records
            </a>
        </div>

        <!-- Divider -->
        <div class="section-divider"></div>

        <!-- Documents Section -->
        <h2>Confirmed Documents</h2>
        <?php if (!$is_approved): ?>
        <div class="approval-warning" style="margin-top: 0;">
            <i class="fas fa-lock"></i>
            <p>Document verification will be available after supervisor approval.</p>
        </div>
        <?php endif; ?>
        <div class="documents-list">
            <?php if ($submitted_docs): ?>
                <?php foreach ($submitted_docs as $doc): ?>
                    <div class="document-item">
                        <div class="document-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="document-info">
                            <h3 class="document-title"><?= htmlspecialchars($doc['document_name']) ?></h3>
                            <div class="document-status">
                                <i class="fas fa-check-circle"></i>
                                <span>Confirmed</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="document-item">
                    <div class="document-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="document-info">
                        <h3 class="document-title">No documents verified yet</h3>
                        <div class="document-status" style="color: var(--text-secondary);">
                            <i class="fas fa-clock"></i>
                            <span>Pending</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
  </div>

  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script>
    AOS.init({ duration: 800, once: true });

    function updateClock() {
        const now = new Date();
        const options = { hour12: true, hour: 'numeric', minute: '2-digit', second: '2-digit' };
        document.getElementById('liveClock').textContent = now.toLocaleTimeString('en-US', options);
    }
    updateClock();
    setInterval(updateClock, 1000);

    function toggleTheme() {
        document.body.classList.toggle('dark');
        const icon = document.querySelector('.toggle-theme i');
        icon.classList.toggle('fa-moon');
        icon.classList.toggle('fa-sun');
        localStorage.setItem('theme', document.body.classList.contains('dark') ? 'dark' : 'light');
    }

    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark');
        document.querySelector('.toggle-theme i').classList.replace('fa-moon', 'fa-sun');
    }

    // Add this to automatically hide the success message after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const successMessage = document.querySelector('.approval-success');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.transition = 'opacity 0.5s ease-out';
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 500);
            }, 5000);
        }
    });

    // Function to get current required hours
    function getRequiredHours() {
        fetch('../actions/update-required-hours.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the required hours display
                document.getElementById('required_hours').textContent = data.hours;
                // Recalculate progress
                updateProgress(data.hours);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Function to update progress based on new required hours
    function updateProgress(newRequiredHours) {
        const totalSeconds = <?= $total_seconds ?>;
        const progressPercentage = Math.min(100, Math.round((totalSeconds / (newRequiredHours * 3600)) * 100));
        document.getElementById('progress_percentage').textContent = progressPercentage + '%';
        document.querySelector('.progress-fill').style.width = progressPercentage + '%';
    }

    // Call getRequiredHours when page loads
    document.addEventListener('DOMContentLoaded', getRequiredHours);
  </script>

  <div style="background-color: #212529; color: white; padding: 1rem 0; text-align: center;">
    © <?php echo date('Y'); ?> Student Progress Tracking System. All rights reserved.
  </div>
</body>
</html>
