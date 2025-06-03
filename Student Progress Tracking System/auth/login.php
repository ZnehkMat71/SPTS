<?php
session_start();
require '../includes/connect.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $identifier = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($identifier) || empty($password)) {
        $error_message = "All fields are required.";
    } else {
        $stmt = $conn->prepare("
            SELECT id, fullname, password, role, identifier
            FROM users
            WHERE identifier = ?
            LIMIT 1
        ");

        if ($stmt) {
            $stmt->bind_param("s", $identifier);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($user_id, $fullname, $hashed_password, $role, $db_identifier);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                    // Clear any existing session data
                    session_unset();
                    session_destroy();
                    session_start();
                    session_regenerate_id(true);

                    // Set session variables
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['fullname'] = $fullname;
                    $_SESSION['identifier'] = $db_identifier;
                    $_SESSION['role'] = $role;
                    $_SESSION['last_activity'] = time();

                    switch ($role) {
                        case 'admin':
                            $_SESSION['admin_logged_in'] = true;
                            header("Location: ../dashboards/admin.php");
                            exit();

                        case 'supervisor':
                            $_SESSION['supervisor_logged_in'] = true;
                            header("Location: ../dashboards/supervisor-dashboard.php");
                            exit();

                        case 'sip_supervisor':
                            $_SESSION['sip_supervisor_logged_in'] = true;
                            $sipStmt = $conn->prepare("SELECT * FROM sip_supervisors WHERE username = ?");
                            $sipStmt->bind_param("s", $db_identifier);
                            $sipStmt->execute();
                            $sipResult = $sipStmt->get_result();
                            if ($sipResult->num_rows > 0) {
                                $_SESSION['sip_supervisor_profile'] = $sipResult->fetch_assoc();
                            }
                            $sipStmt->close();
                            header("Location: ../dashboards/sip-supervisor-dashboard.php");
                            exit();

                        case 'student':
                            $_SESSION['student_logged_in'] = true;
                            
                            $studentStmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
                            $studentStmt->bind_param("s", $db_identifier);
                            $studentStmt->execute();
                            $studentResult = $studentStmt->get_result();
                            
                            if ($studentResult->num_rows > 0) {
                                $student = $studentResult->fetch_assoc();
                                $_SESSION['student_profile'] = $student;
                        
                                // ✅ Critical fix here
                                $_SESSION['student_id'] = $student['student_id'];
                            }
                            
                            $studentStmt->close();
                            header("Location: ../dashboards/student-dashboard.php");
                            exit();
                        

                        default:
                            $error_message = "Unknown role: '$role'. Please contact administrator.";
                            session_destroy();
                    }
                } else {
                    $error_message = "Invalid password.";
                }
            } else {
                $error_message = "User not found.";
            }

            $stmt->close();
        } else {
            $error_message = "Database error. Please try again later.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Login - SPTS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
  :root {
    --primary-color: #007BFF;
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
        background: linear-gradient(135deg,#1E3C72, #2A5298);
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
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(135deg, rgba(1, 51, 104, 0.94), rgba(85, 150, 224, 0.66));
        z-index: -1;
    }

    .login-container {
        width: 100%;
        max-width: 450px;
        padding: 40px;
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
        border: 1px solid var(--border-color);
        margin: 20px;
        text-align: center;
    }

    .login-header h2 {
        font-size: 2.5em;
        margin-bottom: 10px;
        color: var(--text-color);
        font-weight: 600;
    }

    .login-header p,
    .welcome-text {
        color: rgba(255, 255, 255, 0.8);
        font-size: 1.1em;
        margin-bottom: 25px;
        line-height: 1.4;
    }

    .form-group {
        margin-bottom: 20px;
        position: relative;
    }

    .form-group i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255, 255, 255, 0.7);
        z-index: 1;
    }

    .form-control {
        width: 100%;
        padding: 12px 15px 12px 45px;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        color: var(--text-color);
        font-size: 16px;
        transition: 0.3s ease;
        box-sizing: border-box;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        background: rgba(255, 255, 255, 0.15);
        box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2);
    }

    .form-control::placeholder {
        color: rgba(255, 255, 255, 0.7);
    }

    .password-group input {
        padding-right: 40px;
    }

    #togglePassword {
        position: absolute;
        top: 50%;
        right: 15px;
        left: auto; /* Neutralize inherited left positioning */
        transform: translateY(-50%);
        cursor: pointer;
        color: rgba(255, 255, 255, 0.7);
        z-index: 1;
    }

    .btn-login {
        width: 100%;
        padding: 12px;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 10px;
    }

    .btn-login:hover {
        background: #006AE0;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
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

    .register-prompt {
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
        color: var(--text-color);
    }

    .register-link {
        color: #00BFFF;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
        padding: 5px 10px;
        border-radius: 5px;
    }

    .register-link:hover {
        color: rgb(221, 221, 221);
        background: rgba(0, 123, 255, 0.1);
    }

    .home-button,
    .close-btn {
        position: fixed;
        color: white;
        text-decoration: none;
        font-weight: 600;
        padding: 10px 20px;
        border-radius: 5px;
        background: rgba(255, 255, 255, 0.1);
        transition: 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        z-index: 1000;
        backdrop-filter: blur(5px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .home-button {
        top: 20px;
        left: 20px;
    }

    .close-btn {
        top: 10px;
        right: 10px;
        padding: 5px 10px;
    }

    .home-button:hover,
    .close-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .home-button i,
    .close-btn i {
        font-size: 1.2em;
    }

    @media (max-width: 600px) {
        .login-container {
            margin: 15px;
            padding: 30px 20px;
        }

        .login-header h2 {
            font-size: 2em;
        }

        .login-header p {
            font-size: 1em;
        }
    }


    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>Login to Your Account</h2>
            <p class="welcome-text">Welcome back! Please enter your credentials to access your SPTS dashboard.</p>
            <a href="../index.php" class="close-btn"><i class="fas fa-times"></i></a>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" class="form-control" placeholder="Username or Student ID" required>
            </div>

           <div class="form-group password-group">
                <i class="fas fa-lock lock-icon"></i>
                <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                <i class="fas fa-eye toggle-password" id="togglePassword"></i>
            </div>


            <button type="submit" name="login" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <div class="register-prompt">
            <p>Don't have an account? <a href="user-signup.php" class="register-link">Register here</a></p>
        </div>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', () => {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            togglePassword.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>
