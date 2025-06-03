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

// Add cache control headers
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
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor' || !isset($_SESSION['supervisor_logged_in'])) {
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
    
    header("Location: ../index.php");
    exit();
}

// Fetch supervisor data
$supervisor_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT s.fullname, s.course, s.username, s.required_hours 
    FROM supervisors s
    INNER JOIN users u ON u.identifier = s.username
    WHERE u.id = ? AND u.role = 'supervisor'
");
$stmt->bind_param("i", $supervisor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

$supervisor = $result->fetch_assoc();
$supervisor_course = $supervisor['course'];
$course_name = $supervisor_course;
$current_required_hours = $supervisor['required_hours'];
$supervisor_username = $supervisor['username'];

$stmt->close();

// CSRF token handling
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle AJAX requests for updating required hours
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
            throw new Exception('Unauthorized: Missing session data');
        }

        if ($_POST['action'] === 'update') {
            if ($_SESSION['role'] !== 'supervisor' && $_SESSION['role'] !== 'admin') {
                throw new Exception('Unauthorized to update hours');
            }

            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                throw new Exception('Invalid security token');
            }

            $new_hours = intval($_POST['hours']);
            if ($new_hours < 1 || $new_hours > 2000) {
                throw new Exception('Hours must be between 1 and 2000');
            }

            // Update required hours in supervisors table
            $stmt = $conn->prepare("UPDATE supervisors SET required_hours = ? WHERE username = ?");
            $stmt->bind_param("is", $new_hours, $supervisor_username);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Required hours updated successfully',
                    'hours' => $new_hours
                ]);
            } else {
                throw new Exception('Failed to update required hours');
            }
        } else if ($_POST['action'] === 'get') {
            echo json_encode([
                'status' => 'success',
                'hours' => $current_required_hours
            ]);
        } else {
            throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        error_log("Error in supervisor-dashboard.php AJAX: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

// Student approval handling
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['student_id'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security validation failed";
        header("Location: supervisor-dashboard.php");
        exit();
    }

    $student_id = intval($_POST['student_id']);
    $action = isset($_POST['approve_student']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE students SET is_approved = ? WHERE id = ?");
    $stmt->bind_param("ii", $action, $student_id);
    
    if (!$stmt->execute()) {
        $_SESSION['error'] = "Error updating student status";
    }
    
    $stmt->close();
    header("Location: supervisor-dashboard.php");
    exit();
}

// Document list and student data fetching
$required_documents = [
    'QF-SIP-01 Student Internship Program Registration Form',
    'QF-SIP-02 Student Internship Program Agreement',
    'QF-SIP-03 SIP Center Data Sheet',
    'QF-SIP-04 SIP Experience Record',
    'QF-SIP-05 Student Internship Program Evaluation Report',
    'QF-SIP-06 SIP Intern Clearance',
    'Letter of Endorsement 2025',
    'Letter of Intent with Draft MOA 2025',
    'Letter of Intent with Existing MOA 2025',
    'MOA',
    'MOU'
];

// Get sorting parameters from URL
$valid_sort_cols = ['student_id', 'student_name', 'school_year', 'sip_center', 'total_hours', 'status'];
$sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], $valid_sort_cols) ? $_GET['sort_by'] : 'student_name';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_order']) : 'ASC';

// Map sort_by parameter to database column/alias
$sort_map = [
    'student_id' => 'u.identifier',
    'student_name' => 'u.fullname',
    'school_year' => 's.school_year',
    'sip_center' => 's.sip_center',
    'total_hours' => 'total_seconds', // Sort by seconds for hours
    'status' => 's.is_approved'
];

$order_by_clause = "ORDER BY " . $sort_map[$sort_by] . " " . $sort_order;

// fetch student data
function fetchStudentsData($conn, $course, $required_hours, $order_by_clause) {
    $stmt = $conn->prepare("
        SELECT s.id AS student_id, u.identifier, u.fullname AS student_name, s.is_approved, s.school_year, s.sip_center,
            COALESCE(SUM(
                CASE
                    WHEN tt.clock_out IS NULL THEN 0
                    ELSE TIMESTAMPDIFF(SECOND, tt.clock_in, tt.clock_out)
                END
            ), 0) AS total_seconds
        FROM students s
        INNER JOIN users u ON s.student_id = u.identifier
        LEFT JOIN time_tracking tt ON tt.student_id = s.student_id
        WHERE s.course = ?
        GROUP BY s.id
        " . $order_by_clause);
    $stmt->bind_param("s", $course);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];

    while ($row = $result->fetch_assoc()) {
        $seconds = max(0, $row['total_seconds']);
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $row['total_duration'] = sprintf("%02d:%02d", $hours, $minutes);
        $row['progress_percentage'] = $required_hours > 0 
            ? min(100, floor(($seconds / ($required_hours * 3600)) * 100))
            : 0;

        $students[] = $row;
    }
    
    return $students;
}

$students_data = fetchStudentsData($conn, $supervisor_course, $current_required_hours, $order_by_clause);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- Add meta tags to prevent caching -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
  <title>Supervisor Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link href="../css/supervisor-dashboard.css" rel="stylesheet">
</head>
<body>
  <div class="header p-3 d-flex justify-content-between align-items-center">
  <div class="d-flex align-items-center">
    <i class="fas fa-tachometer-alt me-2"></i>
    <h4 class="mb-0">Supervisor Dashboard</h4>
  </div>
  <div class="supervisor-info">
    <span class="supervisor-name">
      <i class="fas fa-user-tie"></i>
      <?= htmlspecialchars($supervisor['fullname']) ?>
    </span>
    <span class="me-3">Required Hours: <span id="current_required_hours"><?php echo $current_required_hours; ?></span></span>
    <button class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#updateHoursModal">
      <i class="fas fa-edit"></i> Update Hours
    </button>
    <a href="?action=logout" class="btn btn-outline-light">
      <i class="fas fa-sign-out-alt me-2"></i>Logout
    </a>
  </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3">
  <div id="toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <i class="fas fa-info-circle me-2"></i>
      <strong class="me-auto" id="toast-title">Notification</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body" id="toast-message"></div>
  </div>
</div>

<!-- Update Hours Modal -->
<div class="modal fade" id="updateHoursModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Required Hours for Course: <?= htmlspecialchars($supervisor_course) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="updateHoursForm" onsubmit="return false;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3">
                        <label for="new_hours" class="form-label">New Required Hours</label>
                        <input type="number" class="form-control" id="new_hours" name="hours" min="1" max="2000" value="<?php echo $current_required_hours; ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary" onclick="updateRequiredHours()">Update Hours</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Main content (no extra top space) -->
<div class="content-wrapper px-3" style="margin-top: 1rem;">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0"> <i class="fas fa-users me-2"></i><?= htmlspecialchars($course_name) ?> Interns</h5>
      <span class="badge bg-primary"><?= count($students_data) ?> Students</span>
    </div>
    <div class="d-flex justify-content-end px-3 py-3" style="background-color: #f8f9fa;">
      <div class="search-container" style="max-width: 300px; width: 100%;">
        <input type="text" id="searchInput" class="form-control"
               placeholder="Search by Student ID or Name" onkeyup="searchTable()">
      </div>
    </div>

    <div class="card-body p-0">
      <?php if (empty($students_data)): ?>
        <div class="alert alert-warning m-3 text-center">No students found for your course.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>
                  <a href="?sort_by=student_id&sort_order=<?= $sort_by === 'student_id' && $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>&<?= http_build_query($_GET, '', '&', PHP_QUERY_RFC3986) ?>" class="sortable-header">
                    Student ID
                    <?php if ($sort_by === 'student_id'): ?>
                      <i class="fas fa-sort-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>"></i>
                    <?php endif; ?>
                  </a>
                </th>
                <th>
                  <a href="?sort_by=student_name&sort_order=<?= $sort_by === 'student_name' && $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>&<?= http_build_query($_GET, '', '&', PHP_QUERY_RFC3986) ?>" class="sortable-header">
                    Student Name
                    <?php if ($sort_by === 'student_name'): ?>
                      <i class="fas fa-sort-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>"></i>
                    <?php endif; ?>
                  </a>
                </th>
                <th>
                  <a href="?sort_by=school_year&sort_order=<?= $sort_by === 'school_year' && $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>&<?= http_build_query($_GET, '', '&', PHP_QUERY_RFC3986) ?>" class="sortable-header">
                    School Year
                    <?php if ($sort_by === 'school_year'): ?>
                      <i class="fas fa-sort-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>"></i>
                    <?php endif; ?>
                  </a>
                </th>
                <th>SIP Center</th>
                <th>
                  <a href="?sort_by=total_hours&sort_order=<?= $sort_by === 'total_hours' && $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>&<?= http_build_query($_GET, '', '&', PHP_QUERY_RFC3986) ?>" class="sortable-header">
                    Total Hours
                    <?php if ($sort_by === 'total_hours'): ?>
                      <i class="fas fa-sort-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>"></i>
                    <?php endif; ?>
                  </a>
                </th>
                <th>Progress</th>
                <th>
                  <a href="?sort_by=status&sort_order=<?= $sort_by === 'status' && $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>&<?= http_build_query($_GET, '', '&', PHP_QUERY_RFC3986) ?>" class="sortable-header">
                    Status
                    <?php if ($sort_by === 'status'): ?>
                      <i class="fas fa-sort-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>"></i>
                    <?php endif; ?>
                  </a>
                </th>
                <th>Action</th>
                <th>Checklist</th>
                <th>Submissions</th>
                <th>Records</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($students_data as $student): ?>
              <?php
                // Submission progress
                $stmt = $conn->prepare("SELECT COUNT(*) AS submitted FROM document_checklist WHERE student_identifier = ? AND is_checked = 1");
                $stmt->bind_param("s", $student['identifier']);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $submitted_docs = (int)$result['submitted'];
                $total_docs = count($required_documents);
                $submission_percentage = round(($submitted_docs / $total_docs) * 100);
                $stmt->close();

                // Hours progress
                [$hours, $minutes] = explode(':', $student['total_duration']);
                $total_minutes = ($hours * 60) + $minutes;
                $worked_percentage = min(100, round(($total_minutes / (300 * 60)) * 100));
              ?>
              <tr>
                <td>
                  <span class="badge bg-secondary"><?= htmlspecialchars($student['identifier']) ?></span>
                </td>
                <td><?= htmlspecialchars($student['student_name']) ?></td>
                <td>
                  <span class="badge bg-primary"><?= htmlspecialchars($student['school_year'] ?? 'N/A') ?></span>
                </td>
                <td>
                  <span class="badge bg-secondary"><?= htmlspecialchars($student['sip_center'] ?? 'N/A') ?></span>
                </td>
                <td>
                  <span class="badge bg-info" style="font-size: 1rem; padding: 0.5rem 1rem;">
                    <i class="fas fa-clock me-2"></i><?= $student['total_duration'] ?> Hours
                  </span>
                </td>
                <td style="min-width: 150px;">
                  <div class="progress-container">
                    <div class="progress">
                      <div class="progress-bar bg-info" role="progressbar" style="width: <?= $student['progress_percentage'] ?>%;">
                      </div>
                    </div>
                    <div class="progress-percentage">
                      <?= $student['progress_percentage'] ?>% Complete
                    </div>
                  </div>
                </td>
                <td>
                  <span class="status-badge <?= $student['is_approved'] ? 'status-approved' : 'status-not-approved' ?>">
                    <i class="fas fa-<?= $student['is_approved'] ? 'check-circle' : 'times-circle' ?> me-1"></i>
                    <?= $student['is_approved'] ? 'Approved' : 'Not Approved' ?>
                  </span>
                </td>
                <td>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="student_id" value="<?= $student['student_id'] ?>">
                    <button type="submit" name="<?= $student['is_approved'] ? 'unapprove_student' : 'approve_student' ?>"
                      class="btn btn-sm btn-<?= $student['is_approved'] ? 'danger' : 'success' ?>">
                      <i class="fas fa-<?= $student['is_approved'] ? 'ban' : 'check' ?> me-1"></i>
                      <?= $student['is_approved'] ? 'Unapprove' : 'Approve' ?>
                    </button>
                  </form>
                </td>
                <td>
                  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#checklistModal<?= $student['student_id'] ?>">
                    <i class="fas fa-clipboard-list me-1"></i>Checklist
                  </button>
                </td>
                <td style="min-width: 150px;">
                  <div class="progress-container">
                    <div class="progress">
                      <div class="progress-bar bg-success" role="progressbar" style="width: <?= $submission_percentage ?>%;">
                      </div>
                    </div>
                    <div class="progress-percentage">
                      <?= $submission_percentage ?>% Submitted
                    </div>
                  </div>
                </td>
                <td>
                  <a href="../views/daily-time-records.php?student_id=<?= urlencode($student['identifier']) ?>" 
                     class="btn btn-sm btn-primary">
                    <i class="fas fa-eye me-1"></i>View
                  </a>
                </td>
              </tr>

              <!-- Checklist Modal -->
              <div class="modal fade" id="checklistModal<?= $student['student_id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">
                        <i class="fas fa-clipboard-list me-2"></i>
                        Checklist - <?= htmlspecialchars($student['student_name']) ?>
                      </h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <ul class="list-group">
                        <?php foreach ($required_documents as $doc):
                          $stmt = $conn->prepare("SELECT is_checked FROM document_checklist WHERE student_identifier = ? AND document_name = ?");
                          $stmt->bind_param("ss", $student['identifier'], $doc);
                          $stmt->execute();
                          $res = $stmt->get_result()->fetch_assoc();
                          $checked = $res ? (bool)$res['is_checked'] : false;
                          $stmt->close();
                        ?>
                          <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?= htmlspecialchars($doc) ?></span>
                            <input type="checkbox" class="form-check-input checklist-toggle"
                              data-student="<?= $student['identifier'] ?>"
                              data-document="<?= htmlspecialchars($doc, ENT_QUOTES) ?>"
                              <?= $checked ? 'checked' : '' ?>>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<footer class="footer">
  © <?php echo date('Y'); ?> Student Progress Tracking System. All rights reserved.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.checklist-toggle').forEach(checkbox => {
    checkbox.addEventListener('change', function () {
      fetch('update-checklist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          student_id: this.dataset.student,
          document_name: this.dataset.document,
          is_checked: this.checked ? 1 : 0
        })
      })
      .then(res => res.json())
      .then(data => {  // Fixed: Added missing parentheses
        if (!data.success) alert("Checklist update failed: " + data.message);
      })
      .catch(err => alert("AJAX error: " + err));
    });
  });
});
function searchTable() {
  const input = document.getElementById("searchInput").value.toLowerCase();
  const table = document.querySelector("table");
  const rows = table.querySelectorAll("tbody tr");
  let visibleCount = 0;

  rows.forEach(function(row) {
    // Skip the "No matching students found." row
    if (row.id === "noResultsRow") return;

    const studentId = row.querySelector("td:nth-child(1)").textContent.toLowerCase();
    const studentName = row.querySelector("td:nth-child(2)").textContent.toLowerCase();

    if (studentId.includes(input) || studentName.includes(input)) {
      row.style.display = "";
      visibleCount++;
    } else {
      row.style.display = "none";
    }
  });

  // Show or hide the "no results" row
  const noResultsRow = document.getElementById("noResultsRow");
  if (noResultsRow) {
    noResultsRow.style.display = visibleCount === 0 ? "" : "none";
  }
}

// Function to show toast message
function showToast(title, message, isSuccess = true) {
    const toast = document.getElementById('toast');
    const toastTitle = document.getElementById('toast-title');
    const toastMessage = document.getElementById('toast-message');
    
    // Set toast content
    toastTitle.textContent = title;
    toastMessage.textContent = message;
    
    // Set toast style based on success/error
    toast.className = `toast ${isSuccess ? 'bg-success' : 'bg-danger'} text-white`;
    
    // Show toast
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
}

// Function to update required hours via AJAX
function updateRequiredHours() {
    const hours = document.getElementById('new_hours').value;
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;

    if (!hours || hours < 1 || hours > 2000) {
        showToast('Error', 'Hours must be between 1 and 2000', false);
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('hours', hours);
    formData.append('csrf_token', csrfToken);

    fetch('supervisor-dashboard.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('current_required_hours').textContent = data.hours;
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('updateHoursModal'));
            modal.hide();
            // Show success message
            showToast('Success', data.message, true);
            // Refresh the page to update student progress
            location.reload();
        } else {
            showToast('Error', data.message || 'Failed to update required hours', false);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'An error occurred while updating required hours. Please try again.', false);
    });
}

// Function to get current required hours
function getRequiredHours() {
    const formData = new FormData();
    formData.append('action', 'get');

    fetch('supervisor-dashboard.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('current_required_hours').textContent = data.hours;
        } else {
            showToast('Error', data.message || 'Failed to get current required hours', false);
        }
    })
    .catch(error => {
        console.error('Error getting hours:', error);
        showToast('Error', 'Failed to get current required hours', false);
    });
}

// Call getRequiredHours when page loads
document.addEventListener('DOMContentLoaded', getRequiredHours);
</script>

<script>
// Provided script
document.querySelectorAll('.update-hours-form').forEach(form => {
  form.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch('update-required-hours.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      showToast(data.status === 'success' ? 'Success' : 'Error', data.message, data.status === 'success');
      if (data.status === 'success') location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'An error occurred during update.', false);
    });
  });
});

const supervisorForm = document.getElementById('supervisor-update-hours-form');
if (supervisorForm) {
  supervisorForm.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append('action', 'update');

    fetch('../actions/update-required-hours.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      showToast(data.status === 'success' ? 'Success' : 'Error', data.message, data.status === 'success');
       if (data.status === 'success') location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'An error occurred during update.', false);
    });
  });
}

</script>
</body>
</html>

