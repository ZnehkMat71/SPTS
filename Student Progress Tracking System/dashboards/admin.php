<?php
// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ob_start(); // Start output buffering
session_start();

// Set security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

require_once '../includes/connect.php';

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

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['admin_logged_in'])) {
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
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    header("Location: ../index.php");
    exit();
}

// Get counts for dashboard
$student_count = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$supervisor_count = $conn->query("SELECT COUNT(*) as count FROM supervisors")->fetch_assoc()['count'];
$sip_supervisor_count = $conn->query("SELECT COUNT(*) as count FROM sip_supervisors")->fetch_assoc()['count'];

// Get recent activities
$recent_students = $conn->query("SELECT * FROM students ORDER BY created_at DESC LIMIT 5");
$recent_supervisors = $conn->query("SELECT * FROM supervisors ORDER BY created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="sidebar">
        <h3 class="text-white text-center mb-4">Admin Panel</h3>
        <a href="admin.php" class="<?php echo !isset($_GET['page']) ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="admin.php?page=students" class="<?php echo isset($_GET['page']) && $_GET['page'] === 'students' ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate"></i> Students
        </a>
        <a href="admin.php?page=supervisors" class="<?php echo isset($_GET['page']) && $_GET['page'] === 'supervisors' ? 'active' : ''; ?>">
            <i class="fas fa-chalkboard-teacher"></i> Supervisors
        </a>
        <a href="admin.php?page=sip_supervisors" class="<?php echo isset($_GET['page']) && $_GET['page'] === 'sip_supervisors' ? 'active' : ''; ?>">
            <i class="fas fa-user-tie"></i> SIP Supervisors
        </a>
        <a href="admin.php?logout=1" class="mt-auto logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="main-content">
        <?php if (!isset($_GET['page'])): ?>
        <!-- Dashboard Content -->
        <div class="welcome-section animate__animated animate__fadeIn">
            <h2>Welcome to Admin Dashboard</h2>
            <p>Manage your students, supervisors, and SIP supervisors from here.</p>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card stat-card bg-primary animate__animated animate__fadeInLeft">
                    <div class="card-body">
                        <h5 class="card-title">Total Students</h5>
                        <h2 class="display-4"><?php echo $student_count; ?></h2>
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-success animate__animated animate__fadeInUp">
                    <div class="card-body">
                        <h5 class="card-title">Total Supervisors</h5>
                        <h2 class="display-4"><?php echo $supervisor_count; ?></h2>
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-info animate__animated animate__fadeInRight">
                    <div class="card-body">
                        <h5 class="card-title">Total SIP Supervisors</h5>
                        <h2 class="display-4"><?php echo $sip_supervisor_count; ?></h2>
                        <i class="fas fa-user-tie"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="recent-activity animate__animated animate__fadeInLeft">
                    <h4 class="mb-3">Recent Students</h4>
                    <?php while ($student = $recent_students->fetch_assoc()): ?>
                    <div class="activity-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($student['fullname']); ?></strong>
                                <div class="text-muted small"><?php echo htmlspecialchars($student['course']); ?></div>
                            </div>
                            <span class="badge <?php echo $student['is_approved'] ? 'bg-success' : 'bg-warning'; ?>">
                                <?php echo $student['is_approved'] ? 'Approved' : 'Pending'; ?>
                            </span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="recent-activity animate__animated animate__fadeInRight">
                    <h4 class="mb-3">Recent Supervisors</h4>
                    <?php while ($supervisor = $recent_supervisors->fetch_assoc()): ?>
                    <div class="activity-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($supervisor['fullname']); ?></strong>
                                <div class="text-muted small"><?php echo htmlspecialchars($supervisor['course']); ?></div>
                            </div>
                            <span class="badge bg-info"><?php echo $supervisor['required_hours']; ?> hours</span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Page Content -->
        <?php
        $page = $_GET['page'];
        switch($page) {
            case 'students':
                include '../admin/students.php';
                break;
            case 'supervisors':
                include '../admin/supervisors.php';
                break;
            case 'sip_supervisors':
                include '../admin/sip_supervisors.php';
                break;
        }
        ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Get the current page parameter from the URL
            const urlParams = new URLSearchParams(window.location.search);
            const currentPageParam = urlParams.get('page');

            // Add active class to current page link
            $('.sidebar a').each(function() {
                const linkHref = $(this).attr('href');
                const linkUrl = new URL(linkHref, window.location.origin);
                const linkPageParam = linkUrl.searchParams.get('page');

                // Check if it's the dashboard link (no page param) and no page param is set in current URL
                if (linkHref === 'admin.php' && !currentPageParam) {
                    $(this).addClass('active');
                } 
                // Check if the link's page param matches the current page param
                else if (currentPageParam && linkPageParam === currentPageParam) {
                     $(this).addClass('active');
                }
            });

            // Mobile sidebar toggle
            if (window.innerWidth <= 768) {
                $('.sidebar').addClass('active');
            }
        });
    </script>
</body>
</html> 