<?php
session_start();
require '../includes/connect.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Initialize
$error = null;
$action = $_GET['action'] ?? 'view';
$student_id = $_GET['student'] ?? $_POST['student_id'] ?? null;
$log_id = $_GET['log_id'] ?? $_POST['log_id'] ?? null;
$log = [];

// Validate student ID
if (empty($student_id)) {
    die("<div class='alert alert-danger text-center'>Student ID is required.</div>");
}

// Fetch student details
$stmt = $conn->prepare("SELECT fullname, course FROM students WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$stmt->bind_result($fullname, $course);
if ($stmt->fetch()) {
    $student = ['fullname' => $fullname, 'course' => $course];
} else {
    $student = ['fullname' => 'Unknown', 'course' => 'N/A'];
}
$stmt->close();

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $clock_in = $_POST['clock_in'];
    $clock_out = $_POST['clock_out'] ?: null;

    // Validation
    if (strtotime($clock_in) === false) {
        $error = "Invalid clock-in time format.";
    } elseif ($clock_out && strtotime($clock_out) === false) {
        $error = "Invalid clock-out time format.";
    } elseif ($clock_out && strtotime($clock_out) < strtotime($clock_in)) {
        $error = "Clock-out time cannot be before clock-in time.";
    } else {
        $stmt = $conn->prepare("UPDATE time_tracking SET clock_in = ?, clock_out = ? WHERE log_id = ?");
        $stmt->bind_param("ssi", $clock_in, $clock_out, $log_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Time record updated successfully.";
            header("Location: ../dashboards/admin.php#student-" . urlencode($student_id));
            exit();
        } else {
            $error = "Update failed: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle Delete
if ($action === 'delete' && $log_id) {
    $stmt = $conn->prepare("DELETE FROM time_tracking WHERE log_id = ?");
    $stmt->bind_param("i", $log_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Time record deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete time record: " . $conn->error;
    }
    $stmt->close();
    header("Location: ../dashboards/admin.php#student-" . urlencode($student_id));
    exit();
}

// Handle Edit - fetch specific log
if ($action === 'edit' && $log_id) {
    $stmt = $conn->prepare("SELECT log_id, clock_in, clock_out FROM time_tracking WHERE log_id = ?");
    $stmt->bind_param("i", $log_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $log = $result->fetch_assoc();
    } else {
        $_SESSION['error'] = "Log not found.";
        header("Location: ../dashboards/admin.php#student-" . urlencode($student_id));
        exit();
    }
    $stmt->close();
}

// Fetch all logs for this student
$stmt = $conn->prepare("SELECT log_id, clock_in, clock_out FROM time_tracking WHERE student_id = ? ORDER BY clock_in DESC");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>


<!DOCTYPE html>
<html>
<head>
    <title>Admin Time Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        .container {
            max-width: 1200px;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 0.5rem;
            transition: transform 0.2s ease-in-out;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 0.5rem 0.5rem 0 0 !important;
            padding: 1rem;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .btn {
            border-radius: 0.25rem;
            padding: 0.375rem 0.75rem;
            transition: all 0.2s ease-in-out;
            font-weight: 500;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
        }
        
        .student-info {
            background-color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary-color);
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .duration-badge {
            padding: 0.35em 0.65em;
            border-radius: 0.25rem;
            font-size: 0.875em;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .duration-complete {
            background-color: var(--success-color);
            color: white;
        }
        
        .duration-incomplete {
            background-color: var(--danger-color);
            color: white;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        
        .page-title {
            color: var(--primary-color);
            font-weight: 600;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .page-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary-color);
        }
        
        .student-info h5 {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .student-info p {
            margin-bottom: 0.5rem;
        }
        
        .student-info strong {
            color: #495057;
        }
        
        .alert {
            border: none;
            border-radius: 0.5rem;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-outline-danger {
            color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        .btn-outline-danger:hover {
            background-color: var(--danger-color);
            color: white;
        }
        
        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
        }
        
        .empty-state i {
            color: #adb5bd;
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        .table-responsive {
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1.25rem;
            transition: all 0.2s ease-in-out;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }
        
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), #0a58ca);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .stats-card h6 {
            font-size: 0.875rem;
            opacity: 0.8;
            margin-bottom: 0.5rem;
        }
        
        .stats-card h3 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0;
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="page-title">Time Records Management</h3>
        <a href="../dashboards/admin.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stats-card">
                <h6>Total Records</h6>
                <h3><?= count($logs) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card" style="background: linear-gradient(135deg, var(--success-color), #157347);">
                <h6>Completed Sessions</h6>
                <h3><?= count(array_filter($logs, function($log) { return $log['clock_out'] !== null; })) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card" style="background: linear-gradient(135deg, var(--danger-color), #b02a37);">
                <h6>Incomplete Sessions</h6>
                <h3><?= count(array_filter($logs, function($log) { return $log['clock_out'] === null; })) ?></h3>
            </div>
        </div>
    </div>

    <div class="student-info">
        <div class="row">
            <div class="col-md-6">
                <h5 class="mb-3">Student Information</h5>
                <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($student['fullname']) ?></p>
                <p class="mb-1"><strong>Student ID:</strong> <?= htmlspecialchars($student_id) ?></p>
                <p class="mb-0"><strong>Course:</strong> <?= htmlspecialchars($student['course']) ?></p>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($action === 'edit' && $log): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Edit Time Record</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="admin-time-records.php?action=update">
                    <input type="hidden" name="log_id" value="<?= htmlspecialchars($log['log_id']) ?>">
                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_id) ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Clock In</label>
                            <input type="datetime-local" name="clock_in" class="form-control" required
                                   value="<?= date('Y-m-d\TH:i', strtotime($log['clock_in'])) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Clock Out</label>
                            <input type="datetime-local" name="clock_out" class="form-control"
                                   value="<?= $log['clock_out'] ? date('Y-m-d\TH:i', strtotime($log['clock_out'])) : '' ?>">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="../views/admin-time-records.php?student=<?= urlencode($student_id) ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Update Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Time Logs History</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $record): ?>
                                <?php
                                $date = date('Y-m-d', strtotime($record['clock_in']));
                                $in = date('h:i:s A', strtotime($record['clock_in']));
                                $out = $record['clock_out'] ? date('h:i:s A', strtotime($record['clock_out'])) : 'N/A';
                                $duration = 'Incomplete';
                                $durationClass = 'duration-incomplete';
                                $durationIcon = 'bi-clock';
                                if ($record['clock_out']) {
                                    $total_seconds = strtotime($record['clock_out']) - strtotime($record['clock_in']);
                                    $hours = floor($total_seconds / 3600);
                                    $minutes = floor(($total_seconds % 3600) / 60);
                                    $duration = "{$hours}h {$minutes}m";
                                    $durationClass = 'duration-complete';
                                    $durationIcon = 'bi-check-circle';
                                }
                                ?>
                                <tr>
                                    <td><?= $date ?></td>
                                    <td><?= $in ?></td>
                                    <td><?= $out ?></td>
                                    <td>
                                        <span class="duration-badge <?= $durationClass ?>">
                                            <i class="bi <?= $durationIcon ?>"></i>
                                            <?= $duration ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?action=edit&log_id=<?= $record['log_id'] ?>&student=<?= urlencode($student_id) ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Edit Record">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?action=delete&log_id=<?= $record['log_id'] ?>&student=<?= urlencode($student_id) ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete this record?')"
                                               title="Delete Record">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="bi bi-clock-history" style="font-size: 3rem;"></i>
                                    <p class="mt-3">No time records found for this student</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
