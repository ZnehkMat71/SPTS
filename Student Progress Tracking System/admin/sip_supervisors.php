<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $fullname = trim($_POST['fullname']);
                $username = trim($_POST['username']);
                $sip_center = trim($_POST['sip_center']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Check for duplicate username in both sip_supervisors and users tables (case-sensitive)
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sip_supervisors WHERE username = ? 
                                           UNION ALL 
                                           SELECT COUNT(*) FROM users WHERE identifier = ?");
                    $stmt->bind_param("ss", $username, $username);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $count1 = $result->fetch_assoc()['count'];
                    $count2 = $result->fetch_assoc()['count'];
                    
                    if ($count1 > 0 || $count2 > 0) {
                        throw new Exception("Username already exists.");
                    }
                    
                    // Check for duplicate fullname
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sip_supervisors WHERE fullname = ?");
                    $stmt->bind_param("s", $fullname);
                    $stmt->execute();
                    if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
                        throw new Exception("Full name already exists.");
                    }
                    
                    // Insert into sip_supervisors table
                    $sql = "INSERT INTO sip_supervisors (fullname, username, sip_center, password) 
                            VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssss", $fullname, $username, $sip_center, $password);
                    $stmt->execute();
                    
                    // Insert into users table
                    $sql = "INSERT INTO users (fullname, role, identifier, password) 
                            VALUES (?, 'sip_supervisor', ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $fullname, $username, $password);
                    $stmt->execute();
                    
                    $conn->commit();
                    $_SESSION['success'] = "SIP Supervisor added successfully.";
                    header("Location: admin.php?page=sip_supervisors");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error: " . $e->getMessage();
                    header("Location: admin.php?page=sip_supervisors");
                    exit();
                }
                break;

            case 'edit':
                $id = $_POST['id'];
                $fullname = trim($_POST['fullname']);
                $username = trim($_POST['username']);
                $sip_center = trim($_POST['sip_center']);
                $password = trim($_POST['password']);
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Get original username for users table update
                    $stmt = $conn->prepare("SELECT username FROM sip_supervisors WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $original_username = $stmt->get_result()->fetch_assoc()['username'];
                    
                    // Prepare password hash if password is being updated
                    $hashed_password = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;

                    // First update the users table to ensure consistency
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

                    $user_sql .= " WHERE role = 'sip_supervisor' AND identifier = ?";
                    $user_params[] = $original_username;
                    $user_types .= "s";

                    $stmt = $conn->prepare($user_sql);
                    $stmt->bind_param($user_types, ...$user_params);
                    $stmt->execute();

                    // Then update the sip_supervisors table
                    $supervisor_sql = "UPDATE sip_supervisors SET 
                        fullname = ?, 
                        username = ?, 
                        sip_center = ?";
                    
                    $supervisor_params = [$fullname, $username, $sip_center];
                    $supervisor_types = "sss";

                    if ($hashed_password) {
                        $supervisor_sql .= ", password = ?";
                        $supervisor_params[] = $hashed_password;
                        $supervisor_types .= "s";
                    }

                    $supervisor_sql .= " WHERE id = ?";
                    $supervisor_params[] = $id;
                    $supervisor_types .= "i";

                    $stmt = $conn->prepare($supervisor_sql);
                    $stmt->bind_param($supervisor_types, ...$supervisor_params);
                    $stmt->execute();

                    $conn->commit();
                    $_SESSION['success'] = "SIP Supervisor updated successfully.";
                    header("Location: admin.php?page=sip_supervisors");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error: " . $e->getMessage();
                    header("Location: admin.php?page=sip_supervisors");
                    exit();
                }
                break;

            case 'delete':
                $id = $_POST['id'];
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Get supervisor details before deleting
                    $stmt = $conn->prepare("SELECT username FROM sip_supervisors WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $supervisor = $result->fetch_assoc();
                    
                    if (!$supervisor) {
                        throw new Exception("SIP Supervisor not found");
                    }

                    // 1. First, handle all students assigned to this supervisor
                    // Get count of affected students
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE sip_supervisor_id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $student_count = $stmt->get_result()->fetch_assoc()['count'];

                    // Update students to remove supervisor assignment
                    $stmt = $conn->prepare("UPDATE students SET sip_supervisor_id = NULL WHERE sip_supervisor_id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();

                    // 2. Delete from sip_supervisors table
                    $stmt = $conn->prepare("DELETE FROM sip_supervisors WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();

                    // 3. Delete from users table
                    $stmt = $conn->prepare("DELETE FROM users WHERE identifier = ? AND role = 'sip_supervisor'");
                    $stmt->bind_param("s", $supervisor['username']);
                    $stmt->execute();

                    $conn->commit();
                    $_SESSION['success'] = "SIP Supervisor deleted successfully. " . 
                        ($student_count > 0 ? "Note: $student_count students were unassigned from this supervisor." : "");
                    header("Location: admin.php?page=sip_supervisors");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error: " . $e->getMessage();
                    header("Location: admin.php?page=sip_supervisors");
                    exit();
                }
                break;
        }
    }
}

// Display success/error messages and clear them
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>' . $_SESSION['success'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>' . $_SESSION['error'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['error']);
}

// Get supervisor for editing
$edit_supervisor = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM sip_supervisors WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_supervisor = $stmt->get_result()->fetch_assoc();
}

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sip_center_filter = isset($_GET['sip_center']) ? trim($_GET['sip_center']) : '';

// Build the query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(s.fullname LIKE ? OR s.username LIKE ? OR s.sip_center LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= 'sss';
}

if (!empty($sip_center_filter)) {
    $where_conditions[] = "s.sip_center = ?";
    $params[] = $sip_center_filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get all SIP supervisors with their student counts
$sql = "SELECT s.*, COUNT(st.id) as student_count 
        FROM sip_supervisors s 
        LEFT JOIN students st ON s.id = st.sip_supervisor_id 
        $where_clause
        GROUP BY s.id 
        ORDER BY s.fullname";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $supervisors = $stmt->get_result();
} else {
    $supervisors = $conn->query($sql);
}

// Get all SIP centers
$sip_centers = $conn->query("SELECT DISTINCT sip_center FROM sip_supervisors ORDER BY sip_center");
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"><i class="fas fa-building-user me-2"></i>Manage SIP Supervisors</h3>
        <button class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addSupervisorModal">
            <i class="fas fa-plus me-2"></i> Add New SIP Supervisor
        </button>
    </div>

    <!-- Search and Filter Form -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <form method="GET" class="row g-3" id="searchForm">
                <input type="hidden" name="page" value="sip_supervisors">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" name="search" placeholder="Search SIP supervisors..." 
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
                            <i class="fas fa-building text-muted"></i>
                        </span>
                        <select class="form-select border-start-0" name="sip_center" onchange="this.form.submit()">
                            <option value="">All SIP Centers</option>
                            <?php while ($center = $sip_centers->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($center['sip_center']); ?>" 
                                        <?php echo $sip_center_filter === $center['sip_center'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($center['sip_center']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Supervisors Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="border-0">Full Name</th>
                            <th class="border-0">Username</th>
                            <th class="border-0">SIP Center</th>
                            <th class="border-0">Students Assigned</th>
                            <th class="border-0">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sipSupervisorsTableBody">
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
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?php echo htmlspecialchars($supervisor['username']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars($supervisor['sip_center']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-success">
                                        <?php echo $supervisor['student_count']; ?> students
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group" aria-label="SIP Supervisor Actions">
                                        <button class="btn btn-sm btn-outline-primary me-1"
                                                onclick="editSupervisor(<?php echo htmlspecialchars(json_encode($supervisor)); ?>)"
                                                title="Edit Supervisor">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger"
                                                onclick="deleteSupervisor(<?php echo $supervisor['id']; ?>, '<?php echo htmlspecialchars($supervisor['fullname']); ?>')"
                                                title="Delete Supervisor">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($supervisors->num_rows === 0): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-search fa-2x mb-3"></i>
                                        <p class="mb-0">No SIP supervisors found.</p>
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
</div>

<!-- Add Supervisor Modal -->
<div class="modal fade" id="addSupervisorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>Add New SIP Supervisor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
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
                        <label class="form-label">SIP Center</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-building text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="sip_center" required>
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

<!-- Edit Supervisor Modal -->
<div class="modal fade" id="editSupervisorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Edit SIP Supervisor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-user text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="fullname" id="edit_fullname" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-at text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="username" id="edit_username" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">SIP Center</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-building text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="sip_center" id="edit_sip_center" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="password" placeholder="Enter a new password">
                        </div>
                        <small class="text-muted">Leave blank to keep current password.</small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Supervisor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
                <p>Are you sure you want to delete <strong id="delete_name"></strong>?</p>
                <div class="alert alert-danger">
                    <i class="fas fa-info-circle me-2"></i>
                    This will unassign all students from this supervisor.
                </div>
            </div>
            <div class="modal-footer border-0">
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
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

.table-hover tbody tr:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.05);
}
</style>

<script>
function editSupervisor(supervisor) {
    document.getElementById('edit_id').value = supervisor.id;
    document.getElementById('edit_fullname').value = supervisor.fullname;
    document.getElementById('edit_username').value = supervisor.username;
    document.getElementById('edit_sip_center').value = supervisor.sip_center;
    new bootstrap.Modal(document.getElementById('editSupervisorModal')).show();
}

function deleteSupervisor(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteSupervisorModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('searchForm');
    const searchInput = document.getElementById('searchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const sipSupervisorsTableBody = document.getElementById('sipSupervisorsTableBody');

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
            if (loadingSpinner && sipSupervisorsTableBody) {
                sipSupervisorsTableBody.style.display = 'none';
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
            if (loadingSpinner && sipSupervisorsTableBody) {
                 loadingSpinner.style.display = 'none';
                 sipSupervisorsTableBody.style.display = 'table-row-group'; // Or '' depending on default
             }
         });
    }
});
</script>
