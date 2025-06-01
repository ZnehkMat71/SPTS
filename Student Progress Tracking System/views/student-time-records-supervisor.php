<?php
session_start();
require '../includes/connect.php';

// ✅ SIP Supervisor access only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header("Location: ../auth/login.php");
    exit();
}

// ✅ Validate and get student ID
$student_identifier = filter_input(INPUT_GET, 'student_id', FILTER_SANITIZE_STRING);
if (empty($student_identifier)) {
    die("<div class='alert alert-danger text-center'>Invalid request. Student ID is required.</div>");
}

if (!$conn) {
    die("<div class='alert alert-danger text-center'>Connection failed: " . mysqli_connect_error() . "</div>");
}

// ✅ Fetch student name with error checking
$query = "SELECT fullname FROM students WHERE student_id = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}

$stmt->bind_param("s", $student_identifier);
$stmt->execute();
$stmt->bind_result($found_name);

$student_name = "Unknown Student";
if ($stmt->fetch()) {
    $student_name = $found_name;
}

$stmt->close();

// ✅ Fetch time logs
$stmt = $conn->prepare("SELECT clock_in, clock_out, confirmed FROM time_tracking WHERE student_id = ? ORDER BY clock_in DESC");
if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}

$stmt->bind_param("s", $student_identifier);
$stmt->execute();
$stmt->bind_result($clock_in, $clock_out, $confirmed);

$daily_sessions = [];
$daily_hours = [];

while ($stmt->fetch()) {
    $date = date('Y-m-d', strtotime($clock_in));
    $time_in = date('h:i:s A', strtotime($clock_in));
    $time_out = $clock_out ? date('h:i:s A', strtotime($clock_out)) : 'N/A';
    $session_seconds = $clock_out ? (strtotime($clock_out) - strtotime($clock_in)) : 0;
    $daily_hours[$date] = ($daily_hours[$date] ?? 0) + $session_seconds;

    $accumulated_hours = floor($daily_hours[$date] / 3600);
    $accumulated_minutes = floor(($daily_hours[$date] % 3600) / 60);

    if ($confirmed === 1) {
        $status = 'Confirmed';
    } elseif ($confirmed === 0) {
        $status = 'Invalidated';
    } else {
        $status = 'Pending';
    }

    $daily_sessions[] = [
        'date' => $date,
        'login_time' => $time_in,
        'logout_time' => $time_out,
        'accumulated_time' => sprintf('%02d hrs %02d mins', $accumulated_hours, $accumulated_minutes),
        'status' => $status
    ];
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SIP Supervisor - Student Time Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .header {
            background-color: #007bff;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-back {
            background-color: white;
            color: #007bff;
            border: none;
            font-weight: bold;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 4px;
        }
        .card-custom {
            background-color: white;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-top: 30px;
        }
        .card-header-blue {
            background-color: #0d6efd;
            color: white;
            padding: 15px;
            border-top-left-radius: 6px;
            border-top-right-radius: 6px;
        }
        .table td, .table th {
            vertical-align: middle;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.85rem;
            color: white;
        }
        .status-confirmed {
            background-color: #198754;
        }
        .status-pending {
            background-color: #ffc107;
            color: #000;
        }
        .status-invalidated {
            background-color: #dc3545;
        }
    </style>
</head>
<body>

<div class="header">
    <h4 class="m-0">Student Time Records</h4>
    <a href="../dashboards/supervisor-dashboard.php" class="btn-back">&larr; Back to Dashboard</a>
</div>

<div class="container">
    <div class="card card-custom">
        <div class="card-header-blue">
            <h5 class="mb-0"><?= htmlspecialchars($student_name) ?></h5>
            <small>ID: <?= htmlspecialchars($student_identifier) ?></small>
        </div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Login Time</th>
                        <th>Logout Time</th>
                        <th>Accumulated Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daily_sessions as $log): ?>
                        <?php
                            $status = $log['status'];
                            $badgeClass = match ($status) {
                                'Confirmed' => 'status-badge status-confirmed',
                                'Pending' => 'status-badge status-pending',
                                'Invalidated' => 'status-badge status-invalidated',
                                default => 'status-badge'
                            };
                        ?>
                        <tr>
                            <td><strong><?= $log['date'] ?></strong></td>
                            <td><?= $log['login_time'] ?? '-' ?></td>
                            <td><?= $log['logout_time'] ?? '-' ?></td>
                            <td class="text-success"><?= $log['accumulated_time'] ?? '-' ?></td>
                            <td><span class="<?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($daily_sessions)): ?>
                        <tr><td colspan="5" class="text-center text-muted">No time records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
