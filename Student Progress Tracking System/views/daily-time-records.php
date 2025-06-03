<?php
session_start();
require '../includes/connect.php';

// Enhanced session and role verification
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Verify the role is valid and has the correct session flag
$valid_roles = ['student', 'supervisor', 'sip_supervisor'];
if (!in_array($role, $valid_roles)) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Verify role-specific session flag
$role_verified = match ($role) {
    'student' => isset($_SESSION['student_logged_in']),
    'supervisor' => isset($_SESSION['supervisor_logged_in']),
    'sip_supervisor' => isset($_SESSION['sip_supervisor_logged_in']),
    default => false
};

if (!$role_verified) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Set back link based on verified role
$back_link = match ($role) {
    'supervisor' => '../dashboards/supervisor-dashboard.php',
    'sip_supervisor' => '../dashboards/sip-supervisor-dashboard.php',
    'student' => '../dashboards/student-dashboard.php',
    default => '../auth/login.php'
};

// Debug information
error_log("Current Role: " . $role);
error_log("Back Link: " . $back_link);
error_log("Session Role: " . $_SESSION['role']);
error_log("Role Verified: " . ($role_verified ? 'true' : 'false'));

// Store the back link in session to maintain consistency
$_SESSION['last_dashboard'] = $back_link;

// Determine the student identifier
if ($role === 'student') {
    // For students, use their user_id to get their identifier
    $stmt = $conn->prepare("SELECT identifier FROM users WHERE id = ? AND role = 'student' LIMIT 1");
    if (!$stmt) {
        die("<div class='alert alert-danger'>Database error: " . $conn->error . "</div>");
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($student_identifier);
    $stmt->fetch();
    $stmt->close();

    if (empty($student_identifier)) {
        die("<div class='alert alert-danger text-center'>Student identifier not found.</div>");
    }
} else {
    // For supervisors, get student_id from URL parameter
    $student_identifier = filter_input(INPUT_GET, 'student_id', FILTER_SANITIZE_STRING);
    if (empty($student_identifier)) {
        die("<div class='alert alert-danger text-center'>Student ID is required.</div>");
    }
}

// Get student name and verify student exists
try {
    $sql = "SELECT fullname, id FROM users WHERE identifier = ? AND role = 'student' LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Database error: " . $conn->error);

    $stmt->bind_param("s", $student_identifier);
    $stmt->execute();
    $stmt->bind_result($student_name, $student_user_id);
    $stmt->fetch();
    $stmt->close();

    if (empty($student_name)) {
        die("<div class='alert alert-danger text-center'>Student not found.</div>");
    }
} catch (Exception $e) {
    die("<div class='alert alert-danger text-center'>Error: " . $e->getMessage() . "</div>");
}

// Handle POST (only for SIP Supervisors)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'sip_supervisor') {
    try {
        if (!isset($_POST['confirm_ids'])) {
            throw new Exception("No records selected.");
        }

        $confirm_ids = array_map('intval', $_POST['confirm_ids']);
        $placeholders = implode(',', array_fill(0, count($confirm_ids), '?'));

        if (isset($_POST['confirm'])) {
            $confirm_value = 1;  // Confirmed
        } elseif (isset($_POST['unconfirm'])) {
            $confirm_value = -1; // Invalidated
        } else {
            throw new Exception("Invalid action.");
        }

        $sql = "UPDATE time_tracking SET confirmation_status = ? WHERE log_id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Database error: " . $conn->error);

        $types = 'i' . str_repeat('i', count($confirm_ids));
        $params = array_merge([$confirm_value], $confirm_ids);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        header("Location: " . filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL));
        exit();
    } catch (Exception $e) {
        $error_message = "Action failed: " . $e->getMessage();
    }
}

// Fetch logs
try {
    $sql = "SELECT log_id, clock_in, clock_out, confirmation_status 
            FROM time_tracking 
            WHERE student_id = ? 
            ORDER BY clock_in DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Database error: " . $conn->error);

    $stmt->bind_param("s", $student_identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    $daily_sessions = [];
    $daily_hours = [];

    while ($row = $result->fetch_assoc()) {
        $date = date('Y-m-d', strtotime($row['clock_in']));
        $clock_in = strtotime($row['clock_in']);
        $duration = 0;

        if (!empty($row['clock_out'])) {
            $clock_out = strtotime($row['clock_out']);
            $duration = max(0, $clock_out - $clock_in);
        }

        // The status will remain pending unless confirmed
        if ($row['confirmation_status'] == 1) {
            $daily_hours[$date] = ($daily_hours[$date] ?? 0) + $duration;
        }

        $daily_sessions[] = [
            'id' => $row['log_id'],
            'date' => $date,
            'login_time' => date('h:i:s A', $clock_in),
            'logout_time' => !empty($row['clock_out']) ? date('h:i:s A', strtotime($row['clock_out'])) : 'N/A',
            'confirmation_status' => $row['confirmation_status'],
            'duration' => $duration
        ];
    }

    foreach ($daily_sessions as &$session) {
        $total_seconds = $daily_hours[$session['date']] ?? 0;
        $hours = floor($total_seconds / 3600);
        $minutes = floor(($total_seconds % 3600) / 60);
        $session['accumulated_time'] = sprintf('%02d hrs %02d mins', $hours, $minutes);
    }
    unset($session);

    $stmt->close();
} catch (Exception $e) {
    die("<div class='alert alert-danger text-center'>Error: " . $e->getMessage() . "</div>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Time Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .header-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1.5rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }

        .table td {
            vertical-align: middle;
        }

        .confirmed-row { 
            background-color: #e8f5e9;
            transition: background-color 0.3s ease;
        }

        .invalidated-row { 
            background-color: #f8d7da;
            transition: background-color 0.3s ease;
        }

        .pending-row { 
            background-color: #fff3cd;
            transition: background-color 0.3s ease;
        }

        .badge {
            padding: 0.5em 1em;
            font-weight: 500;
        }

        .btn-back {
            background-color: white;
            color: var(--primary-color);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background-color: rgba(255,255,255,0.9);
            transform: translateX(-3px);
        }

        .btn-action {
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-confirm {
            background-color: #28a745;
            border: none;
        }

        .btn-confirm:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }

        .btn-invalidate {
            background-color: #dc3545;
            border: none;
        }

        .btn-invalidate:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }

        .student-info {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .student-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .student-id {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }

        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            margin-top: 0.2em;
        }

        /* Status Badge Styles */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            gap: 0.375rem;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-confirmed {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-invalidated {
            background-color: #fee2e2;
            color: #991b1b;
        }

        body.dark .status-pending {
            background-color: #78350f;
            color: #fef3c7;
        }

        body.dark .status-confirmed {
            background-color: #166534;
            color: #dcfce7;
        }

        body.dark .status-invalidated {
            background-color: #991b1b;
            color: #fee2e2;
        }

        /* Table Styles */
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            text-align: center;
        }

        .table td {
            vertical-align: middle;
            text-align: center;
        }

        .confirmed-row { 
            background-color: #e8f5e9;
            transition: background-color 0.3s ease;
        }

        .invalidated-row { 
            background-color: #f8d7da;
            transition: background-color 0.3s ease;
        }

        .pending-row { 
            background-color: #fff3cd;
            transition: background-color 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2 class="mb-0"><i class="bi bi-clock-history me-2"></i>Student's Daily Time Records</h2>
                </div>
                <div class="col-md-6 text-end">
                    <?php
                    // Set the correct dashboard path based on role
                    $dashboard_link = match ($role) {
                        'supervisor' => '../dashboards/supervisor-dashboard.php',
                        'sip_supervisor' => '../dashboards/sip-supervisor-dashboard.php',
                        'student' => '../dashboards/student-dashboard.php',
                        default => '../auth/login.php'
                    };
                    ?>
                    <a href="<?= htmlspecialchars($dashboard_link) ?>" class="btn btn-back">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div class="student-info">
                    <div class="student-name"><?= htmlspecialchars($student_name) ?></div>
                    <div class="student-id">ID: <?= htmlspecialchars($student_identifier) ?></div>
                </div>
            </div>

            <div class="card-body">
                <form method="POST">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Login Time</th>
                                    <th>Logout Time</th>
                                    <th>Accumulated Time</th>
                                    <th>Status</th>
                                    <?php if ($role === 'sip_supervisor'): ?>
                                        <th>Select</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($daily_sessions as $log): ?>
                                <?php
                                    $row_class = $log['confirmation_status'] == 1 ? 'confirmed-row' : 
                                                ($log['confirmation_status'] == -1 ? 'invalidated-row' : 'pending-row');
                                ?>
                                <tr class="<?= $row_class ?>">
                                    <td><?= htmlspecialchars($log['date']) ?></td>
                                    <td><?= htmlspecialchars($log['login_time']) ?></td>
                                    <td><?= htmlspecialchars($log['logout_time']) ?></td>
                                    <td><?= htmlspecialchars($log['accumulated_time']) ?></td>
                                    <td>
                                        <?php if ($log['confirmation_status'] == 1): ?>
                                            <span class="status-badge status-confirmed">
                                                <i class="fas fa-check-circle"></i> Confirmed
                                            </span>
                                        <?php elseif ($log['confirmation_status'] == -1): ?>
                                            <span class="status-badge status-invalidated">
                                                <i class="fas fa-times-circle"></i> Invalidated
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($role === 'sip_supervisor'): ?>
                                        <td>
                                            <input type="checkbox" name="confirm_ids[]" value="<?= $log['id'] ?>" class="form-check-input">
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($daily_sessions)): ?>
                                <tr>
                                    <td colspan="<?= $role === 'sip_supervisor' ? 6 : 5 ?>" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox me-2"></i>No records found
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($role === 'sip_supervisor' && !empty($daily_sessions)): ?>
                        <div class="mt-4 text-end">
                            <button type="submit" name="confirm" class="btn btn-confirm btn-action me-2">
                                <i class="bi bi-check-lg me-2"></i>Confirm
                            </button>
                            <button type="submit" name="unconfirm" class="btn btn-invalidate btn-action">
                                <i class="bi bi-x-lg me-2"></i>Invalidate
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
