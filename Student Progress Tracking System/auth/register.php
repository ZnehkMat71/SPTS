<?php
session_start();
require '../includes/connect.php';

$message = "";
$user_type = $_GET['type'] ?? 'student';

// Fetch courses
$courses = [];
if ($stmt = $conn->prepare("SELECT DISTINCT course FROM supervisors")) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row['course'];
    }
    $stmt->close();
}

// Fetch SIP centers
$sip_centers = [];
$sipQuery = $conn->query("SELECT DISTINCT sip_center FROM sip_supervisors");
while ($row = $sipQuery->fetch_assoc()) {
    $sip_centers[] = $row['sip_center'];
}

// AJAX: Fetch supervisor by course
if (isset($_POST['fetch_supervisor']) && isset($_POST['course'])) {
    $course = $_POST['course'];
    $stmt = $conn->prepare("SELECT id, fullname FROM supervisors WHERE course = ? LIMIT 1");
    $stmt->bind_param("s", $course);
    $stmt->execute();
    $result = $stmt->get_result();
    $supervisor = $result->fetch_assoc();
    echo json_encode($supervisor ? ['id' => $supervisor['id'], 'fullname' => $supervisor['fullname']] : ['id' => '', 'fullname' => 'No supervisor found']);
    $stmt->close();
    exit();
}

// AJAX: Fetch SIP supervisor by center
if (isset($_POST['fetch_sip_supervisor']) && isset($_POST['sip_center'])) {
    $sip_center = $_POST['sip_center'];
    $stmt = $conn->prepare("SELECT fullname, id FROM sip_supervisors WHERE sip_center = ? LIMIT 1");
    $stmt->bind_param("s", $sip_center);
    $stmt->execute();
    $stmt->bind_result($fullname, $id);
    echo $stmt->fetch() ? json_encode(['fullname' => $fullname, 'id' => $id]) : json_encode(['fullname' => '', 'id' => '']);
    $stmt->close();
    exit();
}

// Add these validation functions at the top after session_start()
function validateStudentId($student_id) {
    // Adjust pattern based on your student ID format
    return preg_match('/^[A-Za-z0-9-]+$/', $student_id);
}

function validateSchoolYear($year) {
    // Format: YYYY-YYYY
    return preg_match('/^\d{4}-\d{4}$/', $year);
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Handle student registration
if (isset($_POST['register_student'])) {
    $student_id = sanitizeInput($_POST['student_id']);
    $fullname = sanitizeInput($_POST['fullname']);
    $supervisor_id = intval($_POST['supervisor']);
    $course = sanitizeInput($_POST['course']);
    $sip_center = sanitizeInput($_POST['sip_center']);
    $sip_supervisor_id = intval($_POST['sip_supervisor_id']);
    $school_year = sanitizeInput($_POST['school_year']);
    $password = $_POST['password'];

    // Validate all inputs
    if (empty($student_id) || empty($fullname) || empty($course) || empty($supervisor_id) || 
        empty($sip_center) || empty($sip_supervisor_id) || empty($school_year) || empty($password)) {
        $message = "Please fill in all required fields.";
    } elseif (!validateStudentId($student_id)) {
        $message = "Invalid student ID format.";
    } elseif (!validateSchoolYear($school_year)) {
        $message = "Invalid school year format. Use YYYY-YYYY (e.g., 2024-2025)";
    } else {
        // Check if student already exists
        $checkStudent = $conn->prepare("SELECT 1 FROM students WHERE student_id = ?");
        $checkStudent->bind_param("s", $student_id);
        $checkStudent->execute();
        $studentExists = $checkStudent->get_result()->num_rows > 0;
        $checkStudent->close();

        // Check if user already exists in the users table
        $checkUser = $conn->prepare("SELECT 1 FROM users WHERE identifier = ?");
        $checkUser->bind_param("s", $student_id);
        $checkUser->execute();
        $userExists = $checkUser->get_result()->num_rows > 0;
        $checkUser->close();

        if ($studentExists || $userExists) {
            $message = "Student ID already exists.";
        } else {
            // Begin transaction
            $conn->begin_transaction();
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert student data
                $stmt = $conn->prepare("INSERT INTO students 
                    (student_id, fullname, supervisor, course, hours, password, sip_center, is_approved, sip_supervisor_id, school_year) 
                    VALUES (?, ?, ?, ?, 0, ?, ?, 0, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Error preparing student insert: " . $conn->error);
                }
                $stmt->bind_param("ssisssis", $student_id, $fullname, $supervisor_id, $course, $hashed_password, $sip_center, $sip_supervisor_id, $school_year);
                if (!$stmt->execute()) {
                    throw new Exception("Error inserting student: " . $stmt->error);
                }
                $stmt->close();

                // Insert user record for student
                $userStmt = $conn->prepare("INSERT INTO users (fullname, role, identifier, password) VALUES (?, 'student', ?, ?)");
                if (!$userStmt) {
                    throw new Exception("Error preparing user insert: " . $conn->error);
                }
                $userStmt->bind_param("sss", $fullname, $student_id, $hashed_password);
                if (!$userStmt->execute()) {
                    throw new Exception("Error inserting user: " . $userStmt->error);
                }
                $userStmt->close();

                // Commit transaction
                $conn->commit();
                $_SESSION['success'] = "Registration successful! Please login.";
                header("Location: login.php");
                exit();
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $message = "Error during registration: " . $e->getMessage();
                error_log("Registration error: " . $e->getMessage());
            }
        }
    }
}
// Handle supervisor registration
elseif (isset($_POST['register_supervisor'])) {
    $fullname = trim($_POST['fullname']);
    $course = trim($_POST['course']);
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $required_hours = intval($_POST['required_hours']);

    $check = $conn->prepare("SELECT 1 FROM supervisors WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $message = "Username already exists.";
    } else {
        $stmt = $conn->prepare("INSERT INTO supervisors (fullname, course, username, password, required_hours) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $fullname, $course, $username, $password, $required_hours); 
        $stmt->execute();
        $stmt->close();

        $userStmt = $conn->prepare("INSERT INTO users (fullname, role, identifier, password) VALUES (?, 'supervisor', ?, ?)");
        $userStmt->bind_param("sss", $fullname, $username, $password);
        $userStmt->execute();
        $userStmt->close();

        header("Location: login.php");
        exit();
    }
    $check->close();
}

// Handle SIP supervisor registration
elseif (isset($_POST['register_sip'])) {
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $sip_center = trim($_POST['sip_center']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT 1 FROM sip_supervisors WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $message = "Username already exists.";
    } else {
        $stmt = $conn->prepare("INSERT INTO sip_supervisors (fullname, username, sip_center, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $fullname, $username, $sip_center, $password);
        $stmt->execute();
        $stmt->close();

        $userStmt = $conn->prepare("INSERT INTO users (fullname, role, identifier, password) VALUES (?, 'sip_supervisor', ?, ?)");
        $userStmt->bind_param("sss", $fullname, $username, $password);
        $userStmt->execute();
        $userStmt->close();

        header("Location: login.php");
        exit();
    }
    $check->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration - SPTS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
      :root {
    --primary-color: #1E90FF; /* Dodger Blue */
    --text-color: #ffffff;
    --border-color: rgba(255, 255, 255, 0.2);
    --input-bg: rgba(255, 255, 255, 0.1);
    --glass-bg: rgba(255, 255, 255, 0.1);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #1E3C72, #2A5298); /* Blue gradient */
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(30, 60, 114, 0.8), rgba(42, 82, 152, 0.8)); /* Matching gradient */
            z-index: -1;
        }

        .container {
            width: 100%;
            max-width: 500px;
            padding: 40px 30px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            border: 1px solid var(--border-color);
            margin: 20px;
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header h2 {
            font-size: 2em;
            margin-bottom: 10px;
            color: var(--text-color);
            font-weight: 600;
        }

        .form-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.1em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: #ffffff;
            font-size: 16px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='white' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 35px;
            cursor: pointer;
        }

        select.form-control option {
            background: #1E3C72;
            color: #ffffff;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(255, 255, 255, 0.15);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-submit:hover {
            background: #1C86EE; /* Slightly lighter blue */
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .login-prompt {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .login-prompt a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .login-prompt a:hover {
            color: #1C86EE;
            text-decoration: underline;
        }

        .error-message {
            background: rgba(255, 59, 48, 0.1);
            color: #ff6b6b;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid rgba(255, 59, 48, 0.2);
        }
        .form-group {
            position: relative; /* Add this for proper icon positioning */
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            cursor: pointer;
            color: rgba(255, 255, 255, 0.7);
            z-index: 1;
            left: auto; /* Override any inherited left positioning */
        }

        /* Add padding to password fields to prevent text overlap */
        input[type="password"] {
            padding-right: 40px;
        }

        @media (max-width: 600px) {
            .container {
                margin: 15px;
                padding: 30px 20px;
            }

            .form-header h2 {
                font-size: 1.8em;
            }

            .form-header p {
                font-size: 1em;
            }
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="form-header">
            <h2><?php 
                switch($user_type) {
                    case 'student':
                        echo 'Student Registration';
                        break;
                    case 'supervisor':
                        echo 'Supervisor Registration';
                        break;
                    case 'sip':
                        echo 'SIP Supervisor Registration';
                        break;
                }
            ?></h2>
            <p>Create your account to get started</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($user_type === 'student'): ?>         
            <form method="POST">
                <div class="form-group">
                    <input type="text" class="form-control" name="student_id" placeholder="Student ID" required>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" name="fullname" placeholder="Full Name" required>
                </div>
                <div class="form-group">
                    <select name="course" id="course" class="form-control" onchange="fetchSupervisor(this.value)" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?= htmlspecialchars($course) ?>"><?= htmlspecialchars($course) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" name="supervisor_name" id="supervisor" placeholder="Academic Supervisor" readonly required>
                    <input type="hidden" name="supervisor" id="supervisor_id" required>
                </div>
                <div class="form-group">
                    <select name="sip_center" id="sip_center" class="form-control" onchange="fetchSupervisorByCenter(this.value)" required>
                        <option value="">Select SIP Center</option>
                        <?php foreach ($sip_centers as $center) : ?>
                            <option value="<?= htmlspecialchars($center) ?>"><?= htmlspecialchars($center) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" id="sip_supervisor_display" placeholder="SIP Supervisor" readonly disabled>
                    <input type="hidden" name="sip_supervisor_id" id="sip_supervisor_id" required>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" name="school_year" placeholder="School Year (e.g., 2024-2025)" required>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" name="password" placeholder="Password" required>
                    <i class="fas fa-eye toggle-password"></i>
                </div>
                <button type="submit" name="register_student" class="btn-submit">
                    <i class="fas fa-user-plus"></i> Register as Student
                </button>
            </form>
        <?php elseif ($user_type === 'supervisor'): ?>
            <form method="POST">
                <div class="form-group">
                    <input type="text" class="form-control" name="fullname" placeholder="Full Name" required>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" name="course" placeholder="Course" required>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" name="username" placeholder="Username" required>
                </div>
                
                <div class="form-group">
                    <input type="number" class="form-control" name="required_hours" placeholder="Required Hours (e.g., 300 or 600)" required>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" name="password" placeholder="Password" required>
                    <i class="fas fa-eye toggle-password"></i>
                </div>
                <button type="submit" name="register_supervisor" class="btn-submit">
                    <i class="fas fa-user-plus"></i> Register as Supervisor
                </button>
            </form>
        <?php elseif ($user_type === 'sip'): ?>
            <form method="POST">
                <div class="form-group">
                    <input type="text" class="form-control" name="fullname" placeholder="Full Name" required>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" name="username" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" name="sip_center" placeholder="SIP Center" required>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" name="password" placeholder="Password" required>
                    <i class="fas fa-eye toggle-password"></i>
                </div>
                <button type="submit" name="register_sip" class="btn-submit">
                    <i class="fas fa-user-plus"></i> Register as SIP Supervisor
                </button>
            </form>
        <?php endif; ?>

        <div class="login-prompt">
            <p>Already have an account? <a href="login.php">Sign In</a></p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function fetchSupervisor(course) {
            if (course !== "") {
                $.post("", { fetch_supervisor: 1, course }, function(response) {
                    const data = JSON.parse(response);
                    $("#supervisor").val(data.fullname);
                    $("#supervisor_id").val(data.id);
                });
            } else {
                $("#supervisor").val("");
                $("#supervisor_id").val("");
            }
        }

        function fetchSupervisorByCenter(center) {
            if (center !== "") {
                $.post("", { fetch_sip_supervisor: 1, sip_center: center }, function(response) {
                    const data = JSON.parse(response);
                    $("#sip_supervisor_display").val(data.fullname);
                    $("#sip_supervisor_id").val(data.id);
                });
            } else {
                $("#sip_supervisor_display").val("");
                $("#sip_supervisor_id").val("");
            }
        }
        document.querySelectorAll('.toggle-password').forEach(icon => {
        icon.addEventListener('click', (e) => {
            const passwordInput = e.target.previousElementSibling;
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            e.target.classList.toggle('fa-eye-slash');
        });
    });
    
    </script>
</body>
</html> 