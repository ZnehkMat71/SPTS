<?php
ob_start(); // Start output buffering

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$course_filter = isset($_GET['course']) ? trim($_GET['course']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build the query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(s.student_id LIKE ? OR s.fullname LIKE ? OR s.course LIKE ? OR s.sip_center LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

if (!empty($course_filter)) {
    $where_conditions[] = "s.course = ?";
    $params[] = $course_filter;
    $types .= 's';
}

if (!empty($status_filter)) {
    $where_conditions[] = "s.is_approved = ?";
    $params[] = ($status_filter === 'approved' ? 1 : 0);
    $types .= 'i';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM students s $where_clause";
$stmt = $conn->prepare($count_sql);
if (!$stmt) {
    die('Prepare failed: ' . $conn->error . '<br>SQL: ' . $count_sql);
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get students with pagination
$sql = "SELECT s.*, s.hours AS student_required_hours, ss.fullname as supervisor_name, 
        ss.required_hours AS supervisor_required_hours, ss.username AS supervisor_username, 
        ss.course as supervisor_course 
        FROM students s 
        LEFT JOIN supervisors ss ON s.supervisor = ss.id 
        $where_clause
        ORDER BY s.created_at DESC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Prepare failed: ' . $conn->error . '<br>SQL: ' . $sql);
}

// Add pagination parameters
$params[] = $records_per_page;
$params[] = $offset;
$types .= 'ii';

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result();

// Get unique courses for filter and dropdowns
$courses_result = $conn->query("SELECT DISTINCT course FROM supervisors ORDER BY course");
$courses_arr = [];
while ($row = $courses_result->fetch_assoc()) {
    $courses_arr[] = $row['course'];
}

// Function to update student course based on supervisor
function updateStudentCourse($conn, $supervisor_id, $new_course) {
    try {
        $conn->begin_transaction();
        
        // Update all students under this supervisor
        $sql = "UPDATE students SET course = ? WHERE supervisor = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_course, $supervisor_id);
        $stmt->execute();
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

// Handle student actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $student_id = $_POST['student_id'];
                    $fullname = trim($_POST['fullname']);
                    $course = trim($_POST['course']);
                    $sip_center = trim($_POST['sip_center']);
                    $password = $_POST['password'];
                    $school_year = trim($_POST['school_year']);
                    
                    // Enhanced validation
                    if (empty($student_id) || empty($fullname) || empty($course) || empty($sip_center) || empty($password) || empty($school_year)) {
                        throw new Exception("All fields are required.");
                    }
                    
                    if (!preg_match('/^\d{4}-\d{4}$/', $school_year)) {
                        throw new Exception("School year must be in YYYY-YYYY format.");
                    }
                    
                    // Start transaction
                    $conn->begin_transaction();
                    
                    // Check for duplicate student_id in both students and users tables
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE student_id = ? 
                                           UNION ALL 
                                           SELECT COUNT(*) FROM users WHERE identifier = ?");
                    $stmt->bind_param("ss", $student_id, $student_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $count1 = $result->fetch_assoc()['count'];
                    $count2 = $result->fetch_assoc()['count'];
                    
                    if ($count1 > 0 || $count2 > 0) {
                        throw new Exception("Student ID already exists.");
                    }
                    
                    // Check for duplicate fullname
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE fullname = ?");
                    $stmt->bind_param("s", $fullname);
                    $stmt->execute();
                    if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
                        throw new Exception("Full name already exists.");
                    }
                    
                    // Validate course exists and get supervisor
                    $sql = "SELECT id FROM supervisors WHERE course = ? LIMIT 1";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $course);
                    $stmt->execute();
                    $supervisor_result = $stmt->get_result();
                    if ($supervisor_result->num_rows === 0) {
                        throw new Exception("Selected course is not valid.");
                    }
                    $supervisor_id = $supervisor_result->fetch_assoc()['id'];
                    
                    // Validate SIP center exists
                    $sql = "SELECT COUNT(*) as count FROM sip_supervisors WHERE sip_center = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $sip_center);
                    $stmt->execute();
                    if ($stmt->get_result()->fetch_assoc()['count'] === 0) {
                        throw new Exception("Selected SIP center is not valid.");
                    }
                    
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert into students table
                    $sql = "INSERT INTO students (student_id, fullname, course, sip_center, password, school_year, supervisor) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssssi", $student_id, $fullname, $course, $sip_center, $hashed_password, $school_year, $supervisor_id);
                    $stmt->execute();
                    
                    // Insert into users table
                    $sql = "INSERT INTO users (fullname, role, identifier, password) 
                            VALUES (?, 'student', ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $fullname, $student_id, $hashed_password);
                    $stmt->execute();
                    
                    $conn->commit();
                    $_SESSION['success'] = "Student added successfully.";
                    ob_end_clean();
                    header("Location: admin.php?page=students");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error: " . $e->getMessage();
                    ob_end_clean();
                    header("Location: admin.php?page=students");
                    exit();
                }
                break;

            case 'edit':
                try {
                    $original_student_id = $_POST['original_student_id'];
                    $new_student_id = $_POST['new_student_id'];
                    $fullname = trim($_POST['fullname']);
                    $course = trim($_POST['course']);
                    $sip_center = trim($_POST['sip_center']);
                    $school_year = trim($_POST['school_year']);
                    $password = $_POST['password'] ?? '';

                    // Enhanced validation
                    if (empty($new_student_id) || empty($fullname) || empty($course) || empty($sip_center) || empty($school_year)) {
                        throw new Exception("All fields are required.");
                    }

                    if (!preg_match('/^\d{4}-\d{4}$/', $school_year)) {
                        throw new Exception("School year must be in YYYY-YYYY format.");
                    }

                    // Start transaction
                    $conn->begin_transaction();

                    // Get original student data for verification
                    $stmt = $conn->prepare("SELECT s.student_id, s.password as student_password, u.password as user_password, s.supervisor 
                                          FROM students s 
                                          JOIN users u ON u.role = 'student' AND u.identifier = s.student_id 
                                          WHERE s.student_id = ?");
                    $stmt->bind_param("s", $original_student_id);
                    $stmt->execute();
                    $original_data = $stmt->get_result()->fetch_assoc();

                    if (!$original_data) {
                        throw new Exception("Student not found.");
                    }

                    // Only check for duplicate student ID if it's being changed (case-insensitive comparison)
                    if (strtolower($original_student_id) !== strtolower($new_student_id)) {
                        // Check if the new student ID exists in either table
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE LOWER(student_id) = LOWER(?) AND student_id != ?");
                        $stmt->bind_param("ss", $new_student_id, $original_student_id);
                        $stmt->execute();
                        $count1 = $stmt->get_result()->fetch_assoc()['count'];

                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE LOWER(identifier) = LOWER(?) AND role = 'student' AND identifier != ?");
                        $stmt->bind_param("ss", $new_student_id, $original_student_id);
                        $stmt->execute();
                        $count2 = $stmt->get_result()->fetch_assoc()['count'];
                        
                        if ($count1 > 0 || $count2 > 0) {
                            throw new Exception("Student ID already exists.");
                        }

                        // Update users table first to maintain referential integrity
                        $user_sql = "UPDATE users SET 
                            fullname = ?, 
                            identifier = ?";
                        
                        $user_params = [$fullname, $new_student_id];
                        $user_types = "ss";

                        if ($hashed_password) {
                            $user_sql .= ", password = ?";
                            $user_params[] = $hashed_password;
                            $user_types .= "s";
                        }

                        $user_sql .= " WHERE role = 'student' AND identifier = ?";
                        $user_params[] = $original_student_id;
                        $user_types .= "s";

                        $stmt = $conn->prepare($user_sql);
                        $stmt->bind_param($user_types, ...$user_params);
                        $stmt->execute();

                        // Then update the students table
                        $sql = "UPDATE students SET 
                            student_id = ?,
                            fullname = ?,
                            course = ?,
                            sip_center = ?,
                            school_year = ?,
                            supervisor = ?";
                            
                        $params = [$new_student_id, $fullname, $course, $sip_center, $school_year, $supervisor_id];
                        $types = "sssssi";

                        if ($hashed_password) {
                            $sql .= ", password = ?";
                            $params[] = $hashed_password;
                            $types .= "s";
                        }

                        $sql .= " WHERE student_id = ?";
                        $params[] = $original_student_id;
                        $types .= "s";

                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                    } else {
                        // If student ID hasn't changed, just update other fields
                        $sql = "UPDATE students SET 
                            fullname = ?,
                            course = ?,
                            sip_center = ?,
                            school_year = ?,
                            supervisor = ?";
                            
                        $params = [$fullname, $course, $sip_center, $school_year, $supervisor_id];
                        $types = "ssssi";

                        if ($hashed_password) {
                            $sql .= ", password = ?";
                            $params[] = $hashed_password;
                            $types .= "s";
                        }

                        $sql .= " WHERE student_id = ?";
                        $params[] = $original_student_id;
                        $types .= "s";

                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();

                        // Update users table
                        $user_sql = "UPDATE users SET fullname = ?";
                        $user_params = [$fullname];
                        $user_types = "s";

                        if ($hashed_password) {
                            $user_sql .= ", password = ?";
                            $user_params[] = $hashed_password;
                            $user_types .= "s";
                        }

                        $user_sql .= " WHERE role = 'student' AND identifier = ?";
                        $user_params[] = $original_student_id;
                        $user_types .= "s";

                        $stmt = $conn->prepare($user_sql);
                        $stmt->bind_param($user_types, ...$user_params);
                        $stmt->execute();
                    }

                    // Verify the update was successful
                    $stmt = $conn->prepare("SELECT s.student_id as student_identifier, s.password as student_password,
                                          u.identifier as user_identifier, u.password as user_password
                                          FROM students s 
                                          JOIN users u ON u.role = 'student' AND u.identifier = s.student_id 
                                          WHERE s.student_id = ?");
                    $stmt->bind_param("s", $new_student_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    
                    if (!$result) {
                        throw new Exception("Failed to verify student update.");
                    }
                    
                    // Verify student ID and password synchronization
                    if ($result['student_identifier'] !== $result['user_identifier']) {
                        throw new Exception("Failed to synchronize student ID between tables.");
                    }
                    
                    if ($hashed_password && $result['student_password'] !== $result['user_password']) {
                        throw new Exception("Failed to synchronize password between tables.");
                    }

                    $conn->commit();
                    $_SESSION['success'] = "Student updated successfully.";
                    ob_end_clean();
                    header("Location: admin.php?page=students");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error: " . $e->getMessage();
                    ob_end_clean();
                    header("Location: admin.php?page=students");
                    exit();
                }
                break;

            case 'delete':
                try {
                    $student_id = $_POST['student_id'];
                    
                    // Start transaction
                    $conn->begin_transaction();
                    
                    // Get student details before deleting
                    $stmt = $conn->prepare("SELECT student_id, course FROM students WHERE student_id = ?");
                    $stmt->bind_param("s", $student_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $student = $result->fetch_assoc();
                    
                    if (!$student) {
                        throw new Exception("Student not found");
                    }

                    // 1. Delete from students table
                    $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
                    $stmt->bind_param("s", $student_id);
                    $stmt->execute();

                    // 2. Delete from users table
                    $stmt = $conn->prepare("DELETE FROM users WHERE identifier = ? AND role = 'student'");
                    $stmt->bind_param("s", $student_id);
                    $stmt->execute();

                    // 3. Delete any related time tracking records
                    $stmt = $conn->prepare("DELETE FROM time_tracking WHERE student_id = ?");
                    $stmt->bind_param("s", $student_id);
                    $stmt->execute();

                    // 4. Delete any related attendance records
                    $stmt = $conn->prepare("DELETE FROM attendance WHERE student_id = ?");
                    $stmt->bind_param("s", $student_id);
                    $stmt->execute();

                    // 5. Delete any related progress reports
                    $stmt = $conn->prepare("DELETE FROM progress_reports WHERE student_id = ?");
                    $stmt->bind_param("s", $student_id);
                    $stmt->execute();

                    // 6. Delete any related evaluations
                    $stmt = $conn->prepare("DELETE FROM evaluations WHERE student_id = ?");
                    $stmt->bind_param("s", $student_id);
                    $stmt->execute();

                    // 7. Delete any related notifications
                    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ? OR related_id = ?");
                    $stmt->bind_param("ss", $student_id, $student_id);
                    $stmt->execute();

                    $conn->commit();
                    $_SESSION['success'] = "Student deleted successfully.";
                    ob_end_clean();
                    header("Location: admin.php?page=students");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error: " . $e->getMessage();
                    ob_end_clean();
                    header("Location: admin.php?page=students");
                    exit();
                }
                break;

            case 'approve':
                try {
                    $student_id = $_POST['student_id'];
                    
                    // Start transaction
                    $conn->begin_transaction();
                    
                    // Check if student exists
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE student_id = ?");
                    $stmt->bind_param("s", $student_id);
                    $stmt->execute();
                    if ($stmt->get_result()->fetch_assoc()['count'] === 0) {
                        throw new Exception("Student not found.");
                    }
                    
                    $sql = "UPDATE students SET is_approved = 1 WHERE student_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $student_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Error approving student: " . $stmt->error);
                    }
                    
                    $conn->commit();
                    $_SESSION['success'] = "Student approved successfully.";
                    ob_end_clean();
                    header("Location: admin.php?page=students");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error: " . $e->getMessage();
                    ob_end_clean();
                    header("Location: admin.php?page=students");
                    exit();
                }
                break;
        }
    }
}

// Display messages and clear them immediately
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Clear the messages
unset($_SESSION['success']);
unset($_SESSION['error']);

// Get student for editing
$edit_student = null;
if (isset($_GET['edit'])) {
    $student_id = $_GET['edit'];
    $edit_student = $conn->query("SELECT * FROM students WHERE student_id = '$student_id'")->fetch_assoc();
}

// Get unique SIP centers from sip_supervisors
$sip_centers = $conn->query("SELECT DISTINCT sip_center FROM sip_supervisors ORDER BY sip_center");
?>

<div class="container-fluid py-4">
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"><i class="fas fa-user-graduate me-2"></i>Manage Students</h3>
        <button class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="fas fa-plus me-2"></i> Add New Student
        </button>
    </div>

    <!-- Search and Filter Form -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <form method="GET" class="row g-3" id="searchForm">
                <input type="hidden" name="page" value="students">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" name="search" placeholder="Search students..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               id="searchInput">
                        <?php if (!empty($search)): ?>
                            <button type="button" class="btn btn-outline-secondary" id="clearSearchBtn">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-graduation-cap text-muted"></i>
                        </span>
                        <select class="form-select border-start-0" name="course" onchange="this.form.submit()">
                            <option value="">All Courses</option>
                            <?php foreach ($courses_arr as $course): ?>
                                <option value="<?php echo htmlspecialchars($course); ?>" 
                                        <?php echo $course_filter === $course ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-check-circle text-muted"></i>
                        </span>
                        <select class="form-select border-start-0" name="status" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="border-0">Student ID</th>
                            <th class="border-0">Full Name</th>
                            <th class="border-0">Course</th>
                            <th class="border-0">SIP Center</th>
                            <th class="border-0">Required Hours</th>
                            <th class="border-0">Supervisor</th>
                            <th class="border-0">Status</th>
                            <th class="border-0">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="studentsTableBody">
                        <?php while ($student = $students->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($student['student_id']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($student['fullname']); ?></td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    <?php echo htmlspecialchars($student['course']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo htmlspecialchars($student['sip_center']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($student['supervisor_name'] && $student['supervisor_required_hours']): ?>
                                    <span class="badge bg-success">
                                        <?php echo $student['supervisor_required_hours']; ?> hours
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($student['supervisor_name']): ?>
                                    <span class="badge bg-primary">
                                        <?php echo htmlspecialchars($student['supervisor_name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($student['is_approved']): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group" aria-label="Student Actions">
                                    <a href="admin.php?page=students&edit=<?php echo $student['student_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary me-1" title="Edit Student">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if (!$student['is_approved']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success me-1" title="Approve Student">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger me-1" 
                                            title="Delete Student" 
                                            onclick="confirmDeleteStudent('<?php echo $student['student_id']; ?>', '<?php echo htmlspecialchars($student['fullname']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <a href="../views/admin-time-records.php?student=<?php echo urlencode($student['student_id']); ?>" 
                                       class="btn btn-sm btn-outline-secondary" target="_blank" title="View Time Records">
                                        <i class="fas fa-clock"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if ($students->num_rows === 0): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-search fa-2x mb-3"></i>
                                        <p class="mb-0">No students found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="text-center py-4" id="loadingSpinner" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link border-0" href="?page=students&p=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&course=<?php echo urlencode($course_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                <a class="page-link border-0" href="?page=students&p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&course=<?php echo urlencode($course_filter); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link border-0" href="?page=students&p=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&course=<?php echo urlencode($course_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>Add New Student
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Student ID</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-id-card text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="student_id" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-user text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="fullname" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-graduation-cap text-muted"></i>
                            </span>
                            <select class="form-select border-start-0" name="course" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses_arr as $course): ?>
                                    <option value="<?php echo htmlspecialchars($course); ?>">
                                        <?php echo htmlspecialchars($course); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">SIP Center</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-building text-muted"></i>
                            </span>
                            <select class="form-select border-start-0" name="sip_center" required>
                                <option value="">Select SIP Center</option>
                                <?php while ($center = $sip_centers->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($center['sip_center']); ?>">
                                        <?php echo htmlspecialchars($center['sip_center']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" class="form-control border-start-0" name="password" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">School Year</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-calendar text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="school_year" placeholder="YYYY-YYYY" required>
                        </div>
                        <small class="text-muted">Format: YYYY-YYYY (e.g., 2023-2024)</small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($edit_student): ?>
<!-- Edit Student Modal -->
<div class="modal fade show" id="editStudentModal" tabindex="-1" style="display: block;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Edit Student
                </h5>
                <a href="admin.php?page=students" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_student['id']); ?>">
                    <div class="mb-3">
                        <label class="form-label">Student ID</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-id-card text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="new_student_id" 
                                   value="<?php echo htmlspecialchars($edit_student['student_id']); ?>" required>
                            <input type="hidden" name="original_student_id" value="<?php echo htmlspecialchars($edit_student['student_id']); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-user text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="fullname" 
                                   value="<?php echo htmlspecialchars($edit_student['fullname']); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-graduation-cap text-muted"></i>
                            </span>
                            <select class="form-select border-start-0" name="course" required>
                                <?php foreach ($courses_arr as $course): ?>
                                    <option value="<?php echo htmlspecialchars($course); ?>" 
                                            <?php echo ($course === $edit_student['course']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">SIP Center</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-building text-muted"></i>
                            </span>
                            <select class="form-select border-start-0" name="sip_center" required>
                                <option value="">Select SIP Center</option>
                                <?php 
                                $sip_centers->data_seek(0);
                                while ($center = $sip_centers->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo htmlspecialchars($center['sip_center']); ?>"
                                            <?php echo ($center['sip_center'] === $edit_student['sip_center']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($center['sip_center']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="password" 
                                   placeholder="Enter a new password">
                        </div>
                        <small class="text-muted">Leave blank to keep current password.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">School Year</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-calendar text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="school_year" 
                                   value="<?php echo htmlspecialchars($edit_student['school_year']); ?>" required>
                        </div>
                        <small class="text-muted">Format: YYYY-YYYY (e.g., 2023-2024)</small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <a href="admin.php?page=students" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteStudentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete student <strong id="delete_student_name"></strong>?</p>
                <div class="alert alert-danger">
                    <i class="fas fa-info-circle me-2"></i>
                    This action cannot be undone. All associated records will be deleted.
                </div>
            </div>
            <div class="modal-footer border-0">
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="student_id" id="delete_student_id">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.table > :not(caption) > * > * {
    padding: 1rem;
}

.badge {
    padding: 0.5em 0.8em;
    font-weight: 500;
}

.btn-group .btn {
    padding: 0.5rem 0.8rem;
}

.modal-content {
    border-radius: 1rem;
}

.input-group-text {
    border-radius: 0.375rem 0 0 0.375rem;
}

.input-group .form-control {
    border-radius: 0 0.375rem 0.375rem 0;
}

.pagination .page-link {
    border-radius: 0.375rem;
    margin: 0 0.2rem;
}

.pagination .page-item.active .page-link {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
}

.table-hover tbody tr:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.05);
}
</style>

<!-- Add JavaScript for modals and auto-submit filter form -->
<script>
// Add JavaScript for Delete confirmation modal
function confirmDeleteStudent(studentId, studentName) {
    document.getElementById('delete_student_id').value = studentId;
    document.getElementById('delete_student_name').textContent = studentName;
    new bootstrap.Modal(document.getElementById('deleteStudentModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('searchForm');
    const searchInput = document.getElementById('searchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const studentsTableBody = document.getElementById('studentsTableBody');

    if (filterForm) {
        // Add debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Show spinner and hide table when form is submitted
        filterForm.addEventListener('submit', function() {
            if (loadingSpinner && studentsTableBody) {
                studentsTableBody.style.display = 'none';
                loadingSpinner.style.display = 'block';
            }
        });

        // Handle search input with debounce
        if (searchInput) {
            searchInput.addEventListener('input', debounce(function() {
                filterForm.submit();
            }, 500)); // 500ms delay
        }
        
        // Handle select changes immediately
        filterForm.querySelectorAll('select').forEach(function(select) {
            select.addEventListener('change', function() {
                filterForm.submit();
            });
        });

        // Handle clear search button
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function() {
                searchInput.value = '';
                filterForm.submit();
            });
        }

         // Hide spinner and show table when page finishes loading (after potential form submission)
         window.addEventListener('load', function() {
            if (loadingSpinner && studentsTableBody) {
                 loadingSpinner.style.display = 'none';
                 studentsTableBody.style.display = ''; // Or 'table-row-group' depending on default
             }
         });
    }
});
</script> 