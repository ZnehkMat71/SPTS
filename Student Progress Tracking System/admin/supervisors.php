<?php
ob_start(); // Start output buffering

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$course_filter = isset($_GET['course']) ? trim($_GET['course']) : '';

// Build the query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(s.fullname LIKE ? OR s.username LIKE ? OR s.course LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= 'sss';
}

if (!empty($course_filter)) {
    $where_conditions[] = "s.course = ?";
    $params[] = $course_filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM supervisors s $where_clause";
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

// Get supervisors with pagination
$sql = "SELECT s.*, COUNT(st.id) as student_count 
        FROM supervisors s 
        LEFT JOIN students st ON s.id = st.supervisor 
        $where_clause
        GROUP BY s.id 
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
$supervisors = $stmt->get_result();

// Get unique courses for filter
$courses = $conn->query("SELECT DISTINCT course FROM supervisors ORDER BY course");

// Function to update all students' courses when their supervisor's course changes
function updateStudentsCourseOnSupervisorChange($conn, $supervisor_id, $new_course) {
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

// Function to check and update student courses if they don't match their supervisor's course
function syncStudentCoursesWithSupervisors($conn) {
    try {
        $conn->begin_transaction();
        
        // Find all students whose course doesn't match their supervisor's course
        $sql = "UPDATE students s 
                JOIN supervisors ss ON s.supervisor = ss.id 
                SET s.course = ss.course 
                WHERE s.course != ss.course";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

// Handle supervisor actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $fullname = trim($_POST['fullname']);
                $username = trim($_POST['username']);
                $course = trim($_POST['course']);
                $required_hours = (int)$_POST['required_hours'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Check for duplicate username in both supervisors and users tables
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM supervisors WHERE LOWER(username) = LOWER(?) 
                                           UNION ALL 
                                           SELECT COUNT(*) FROM users WHERE LOWER(identifier) = LOWER(?)");
                    $stmt->bind_param("ss", $username, $username);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $count1 = $result->fetch_assoc()['count'];
                    $count2 = $result->fetch_assoc()['count'];
                    
                    if ($count1 > 0 || $count2 > 0) {
                        throw new Exception("Username already exists.");
                    }
                    
                    // Check for duplicate fullname
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM supervisors WHERE fullname = ?");
                    $stmt->bind_param("s", $fullname);
                    $stmt->execute();
                    if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
                        throw new Exception("Full name already exists.");
                    }
                    
                    // Insert into supervisors table
                    $sql = "INSERT INTO supervisors (fullname, username, course, required_hours, password) 
                            VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssis", $fullname, $username, $course, $required_hours, $password);
                    $stmt->execute();
                    
                    // Insert into users table
                    $sql = "INSERT INTO users (fullname, role, identifier, password) 
                            VALUES (?, 'supervisor', ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $fullname, $username, $password);
                    $stmt->execute();
                    
                    $conn->commit();
                    $_SESSION['success'] = "Supervisor added successfully.";
                    ob_end_clean();
                    header("Location: admin.php?page=supervisors");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error: " . $e->getMessage();
                    ob_end_clean();
                    header("Location: admin.php?page=supervisors");
                    exit();
                }
                break;

            case 'edit':
                $id = $_POST['id'];
                $fullname = trim($_POST['fullname']);
                $username = trim($_POST['username']);
                $course = trim($_POST['course']);
                $required_hours = (int)$_POST['required_hours'];
                $password = $_POST['password'] ?? '';

                try {
                    $conn->begin_transaction();

                    // Get original supervisor data for verification
                    $stmt = $conn->prepare("SELECT s.username, s.password as supervisor_password, u.password as user_password, s.course as original_course 
                                          FROM supervisors s 
                                          JOIN users u ON u.role = 'supervisor' AND u.identifier = s.username 
                                          WHERE s.id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $original_data = $stmt->get_result()->fetch_assoc();
                    $original_username = $original_data['username'];
                    $original_course = $original_data['original_course'];

                    // Only check for duplicate username if the username is being changed
                    if (strtolower($original_username) !== strtolower($username)) {
                        // Check if the new username exists in either table
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM supervisors WHERE LOWER(username) = LOWER(?) AND id != ?");
                        $stmt->bind_param("si", $username, $id);
                        $stmt->execute();
                        $count1 = $stmt->get_result()->fetch_assoc()['count'];

                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE LOWER(identifier) = LOWER(?) AND role = 'supervisor' AND identifier != ?");
                        $stmt->bind_param("ss", $username, $original_username);
                        $stmt->execute();
                        $count2 = $stmt->get_result()->fetch_assoc()['count'];
                        
                        if ($count1 > 0 || $count2 > 0) {
                            throw new Exception("Username already exists.");
                        }

                        // Update users table first to maintain referential integrity
                        $user_sql = "UPDATE users SET 
                            fullname = ?, 
                            identifier = ?";
                        
                        $user_params = [$fullname, $username];
                        $user_types = "ss";

                        if ($hashed_password) {
                            $user_sql .= ", password = ?";
                            $user_params[] = $hashed_password;
                            $user_types .= "s";
                        }

                        $user_sql .= " WHERE role = 'supervisor' AND identifier = ?";
                        $user_params[] = $original_username;
                        $user_types .= "s";

                        $stmt = $conn->prepare($user_sql);
                        $stmt->bind_param($user_types, ...$user_params);
                        $stmt->execute();

                        // Then update the supervisors table
                        $supervisor_sql = "UPDATE supervisors SET 
                            fullname = ?, 
                            username = ?, 
                            course = ?, 
                            required_hours = ?";
                        
                        $params = [$fullname, $username, $course, $required_hours];
                        $types = "sssi";

                        if ($hashed_password) {
                            $supervisor_sql .= ", password = ?";
                            $params[] = $hashed_password;
                            $types .= "s";
                        }

                        $supervisor_sql .= " WHERE id = ?";
                        $params[] = $id;
                        $types .= "i";

                        $stmt = $conn->prepare($supervisor_sql);
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                    } else {
                        // If username hasn't changed, just update other fields
                        $supervisor_sql = "UPDATE supervisors SET 
                            fullname = ?, 
                            course = ?, 
                            required_hours = ?";
                        
                        $params = [$fullname, $course, $required_hours];
                        $types = "ssi";

                        if ($hashed_password) {
                            $supervisor_sql .= ", password = ?";
                            $params[] = $hashed_password;
                            $types .= "s";
                        }

                        $supervisor_sql .= " WHERE id = ?";
                        $params[] = $id;
                        $types .= "i";

                        $stmt = $conn->prepare($supervisor_sql);
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

                        $user_sql .= " WHERE role = 'supervisor' AND identifier = ?";
                        $user_params[] = $original_username;
                        $user_types .= "s";

                        $stmt = $conn->prepare($user_sql);
                        $stmt->bind_param($user_types, ...$user_params);
                        $stmt->execute();
                    }

                    // If course has changed, update all students under this supervisor
                    if ($original_course !== $course) {
                        if (!updateStudentsCourseOnSupervisorChange($conn, $id, $course)) {
                            throw new Exception("Failed to update student courses.");
                        }
                    }

                    // Verify the update was successful
                    $stmt = $conn->prepare("SELECT s.username as supervisor_username, s.password as supervisor_password,
                                          u.identifier as user_identifier, u.password as user_password
                                          FROM supervisors s 
                                          JOIN users u ON u.role = 'supervisor' AND u.identifier = s.username 
                                          WHERE s.id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    
                    // Verify username and password synchronization
                    if ($result['supervisor_username'] !== $result['user_identifier']) {
                        throw new Exception("Failed to synchronize username between tables.");
                    }
                    
                    if ($hashed_password && $result['supervisor_password'] !== $result['user_password']) {
                        throw new Exception("Failed to synchronize password between tables.");
                    }

                    $conn->commit();
                    $_SESSION['success'] = "Supervisor updated successfully." . 
                                         ($original_course !== $course ? " Student courses have been updated to match the new course." : "");
                    ob_end_clean();
                    header("Location: admin.php?page=supervisors");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error: " . $e->getMessage();
                    ob_end_clean();
                    header("Location: admin.php?page=supervisors");
                    exit();
                }
                break;

            case 'delete':
                $id = $_POST['id'];
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Get supervisor details before deleting
                    $stmt = $conn->prepare("SELECT username, course FROM supervisors WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $supervisor = $result->fetch_assoc();
                    
                    if (!$supervisor) {
                        throw new Exception("Supervisor not found");
                    }

                    // 1. First, handle all students assigned to this supervisor
                    // Get count of affected students
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE supervisor = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $student_count = $stmt->get_result()->fetch_assoc()['count'];

                    // Update students to remove supervisor assignment
                    $stmt = $conn->prepare("UPDATE students SET supervisor = NULL WHERE supervisor = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();

                    // 2. Delete from supervisors table
                    $stmt = $conn->prepare("DELETE FROM supervisors WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();

                    // 3. Delete from users table
                    $stmt = $conn->prepare("DELETE FROM users WHERE identifier = ? AND role = 'supervisor'");
                    $stmt->bind_param("s", $supervisor['username']);
                    $stmt->execute();

                    // 4. Delete any related time tracking records (if they exist)
                    $stmt = $conn->prepare("DELETE FROM time_tracking WHERE student_id IN (SELECT student_id FROM students WHERE course = ?)");
                    $stmt->bind_param("s", $supervisor['course']);
                    $stmt->execute();

                    // 5. Delete any related attendance records (if they exist)
                    $stmt = $conn->prepare("DELETE FROM attendance WHERE student_id IN (SELECT student_id FROM students WHERE course = ?)");
                    $stmt->bind_param("s", $supervisor['course']);
                    $stmt->execute();

                    // 6. Delete any related progress reports (if they exist)
                    $stmt = $conn->prepare("DELETE FROM progress_reports WHERE student_id IN (SELECT student_id FROM students WHERE course = ?)");
                    $stmt->bind_param("s", $supervisor['course']);
                    $stmt->execute();

                    // 7. Delete any related evaluations (if they exist)
                    $stmt = $conn->prepare("DELETE FROM evaluations WHERE student_id IN (SELECT student_id FROM students WHERE course = ?)");
                    $stmt->bind_param("s", $supervisor['course']);
                    $stmt->execute();

                    // 8. Delete any related notifications (if they exist)
                    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ? OR related_id = ?");
                    $stmt->bind_param("ii", $id, $id);
                    $stmt->execute();

                    $conn->commit();
                    
                    // Set success message with details
                    $_SESSION['success'] = "Supervisor deleted successfully. " . 
                                         ($student_count > 0 ? "Removed supervisor assignment from {$student_count} students." : "");
                    
                    ob_end_clean();
                    header("Location: admin.php?page=supervisors");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error: " . $e->getMessage();
                    ob_end_clean();
                    header("Location: admin.php?page=supervisors");
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

// Get supervisor for editing
$edit_supervisor = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM supervisors WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_supervisor = $stmt->get_result()->fetch_assoc();
}
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
        <h3 class="mb-0"><i class="fas fa-user-tie me-2"></i>Manage Supervisors</h3>
        <button class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addSupervisorModal">
            <i class="fas fa-plus me-2"></i> Add New Supervisor
        </button>
    </div>

    <!-- Search and Filter Form -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <form method="GET" class="row g-3" id="searchForm">
                <input type="hidden" name="page" value="supervisors">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" name="search" placeholder="Search supervisors..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               id="searchInput">
                        <?php if (!empty($search)): ?>
                            <button type="button" class="btn btn-outline-secondary" id="clearSearchBtn">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-graduation-cap text-muted"></i>
                        </span>
                        <select class="form-select border-start-0" name="course" onchange="this.form.submit()">
                            <option value="">All Courses</option>
                            <?php while ($course = $courses->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($course['course']); ?>" 
                                        <?php echo $course_filter === $course['course'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course']); ?>
                                </option>
                            <?php endwhile; ?>
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
                            <th class="border-0">Full Name</th>
                            <th class="border-0">Username</th>
                            <th class="border-0">Course</th>
                            <th class="border-0">Required Hours</th>
                            <th class="border-0">Assigned Students</th>
                            <th class="border-0">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="supervisorsTableBody">
                        <?php while ($supervisor = $supervisors->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle bg-primary text-white me-3">
                                        <?php echo strtoupper(substr($supervisor['fullname'], 0, 1)); ?>
                                    </div>
                                    <?php echo htmlspecialchars($supervisor['fullname']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($supervisor['username']); ?></td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    <?php echo htmlspecialchars($supervisor['course']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo $supervisor['required_hours']; ?> hours
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success">
                                    <?php echo $supervisor['student_count']; ?> students
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group" aria-label="Supervisor Actions">
                                    <a href="admin.php?page=supervisors&edit=<?php echo $supervisor['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary me-1" title="Edit Supervisor">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            title="Delete Supervisor" 
                                            onclick="confirmDeleteSupervisor(<?php echo $supervisor['id']; ?>, '<?php echo htmlspecialchars($supervisor['fullname']); ?>');">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if ($supervisors->num_rows === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-search fa-2x mb-3"></i>
                                        <p class="mb-0">No supervisors found.</p>
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
                <a class="page-link border-0" href="?page=supervisors&p=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&course=<?php echo urlencode($course_filter); ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                <a class="page-link border-0" href="?page=supervisors&p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&course=<?php echo urlencode($course_filter); ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link border-0" href="?page=supervisors&p=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&course=<?php echo urlencode($course_filter); ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- Add Supervisor Modal -->
<div class="modal fade" id="addSupervisorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>Add New Supervisor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
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
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-at text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="username" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-graduation-cap text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="course" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Required Hours</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-clock text-muted"></i>
                            </span>
                            <input type="number" class="form-control border-start-0" name="required_hours" min="1" required>
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
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Supervisor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($edit_supervisor): ?>
<!-- Edit Supervisor Modal -->
<div class="modal fade show" id="editSupervisorModal" tabindex="-1" style="display: block;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Edit Supervisor
                </h5>
                <a href="admin.php?page=supervisors" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $edit_supervisor['id']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-user text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="fullname" 
                                   value="<?php echo htmlspecialchars($edit_supervisor['fullname']); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-at text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="username" 
                                   value="<?php echo htmlspecialchars($edit_supervisor['username']); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-graduation-cap text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="course" 
                                   value="<?php echo htmlspecialchars($edit_supervisor['course']); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Required Hours</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-clock text-muted"></i>
                            </span>
                            <input type="number" class="form-control border-start-0" name="required_hours" min="1" 
                                   value="<?php echo $edit_supervisor['required_hours']; ?>" required>
                        </div>
                        <small class="text-muted">Changing this will update the required hours for all students assigned to this supervisor.</small>
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
                </div>
                <div class="modal-footer border-0">
                    <a href="admin.php?page=supervisors" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Supervisor
                    </button>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteSupervisorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="delete_supervisor_name"></strong>?</p>
                <div class="alert alert-danger">
                    <i class="fas fa-info-circle me-2"></i>
                    This will remove their assignment from all students.
                </div>
            </div>
            <div class="modal-footer border-0">
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_supervisor_id">
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
function confirmDeleteSupervisor(supervisorId, supervisorName) {
    document.getElementById('delete_supervisor_id').value = supervisorId;
    document.getElementById('delete_supervisor_name').textContent = supervisorName;
    new bootstrap.Modal(document.getElementById('deleteSupervisorModal')).show();
}

// Function to clear session messages via AJAX
function clearSessionMessage(type) {
    fetch('clear_session.php?type=' + type, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('searchForm');
    const searchInput = document.getElementById('searchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const supervisorsTableBody = document.getElementById('supervisorsTableBody');

    // Add event listeners for modal closing
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('hidden.bs.modal', function () {
            // Don't clear session messages when modal is closed
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (!alert.classList.contains('d-none')) {
                    alert.classList.remove('d-none');
                }
            });
        });
    });

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
            if (loadingSpinner && supervisorsTableBody) {
                supervisorsTableBody.style.display = 'none';
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
            if (loadingSpinner && supervisorsTableBody) {
                loadingSpinner.style.display = 'none';
                supervisorsTableBody.style.display = 'table-row-group'; // Or '' depending on default
            }
        });
    }
});
</script> 