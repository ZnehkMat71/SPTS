<?php
// select-user-type.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Registration Type</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
       :root {
    --primary-color: #00BFFF; /* DeepSkyBlue */
    --text-color: #ffffff;
    --border-color: rgba(255, 255, 255, 0.2);
    --input-bg: rgba(255, 255, 255, 0.1);
    --glass-bg: rgba(255, 255, 255, 0.1);
    --hover-color: rgba(0, 191, 255, 0.2);
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
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #1E3C72, #2A5298);
            z-index: -1;
        }

        .container {
            width: 100%;
            max-width: 800px;
            padding: 50px 40px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            border: 1px solid var(--border-color);
            margin: 20px;
            text-align: center;
        }

        h2 {
            text-align: center;
            margin-bottom: 15px;
            font-size: 2.5em;
            color: var(--text-color);
            font-weight: 600;
        }

        .subtitle {
            font-size: 1.1em;
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .button-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin: 40px 0;
            padding: 0 20px;
        }

        .user-type-btn {
            padding: 30px 20px;
            font-size: 18px;
            cursor: pointer;
            background: var(--input-bg);
            color: var(--text-color);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .user-type-btn:hover {
            transform: translateY(-5px);
            background: var(--hover-color);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .user-type-btn i {
            font-size: 32px;
            margin-bottom: 5px;
        }

        .user-type-btn .btn-label {
            font-weight: 600;
            font-size: 1.1em;
        }

        .user-type-btn .btn-description {
            font-size: 0.85em;
            opacity: 0.8;
            margin-top: 5px;
        }

        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 1000;
            backdrop-filter: blur(5px);
            border: 1px solid var(--border-color);
        }

        .close-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .close-btn i {
            font-size: 1.2em;
        }

        .login-prompt {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .login-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
            padding: 5px 10px;
            border-radius: 5px;
        }

        .login-link:hover {
            color: #1ca1e3;
            background: rgba(0, 191, 255, 0.1);
        }

        .user-type-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 191, 255, 0.3);
        }

        @media (max-width: 800px) {
            .container {
                margin: 15px;
                padding: 30px 20px;
            }

            .button-container {
                grid-template-columns: 1fr;
                padding: 0;
            }

            .user-type-btn {
                padding: 20px;
            }

            h2 {
                font-size: 2em;
            }

            .subtitle {
                font-size: 1em;
                padding: 0 20px;
            }
        }

    </style>
</head>
<body>
    <div class="container">
        <a href="../index.php" class="close-btn"><i class="fas fa-times"></i></a>
        <div class="welcome-text">
            <h2>Welcome to SPTS</h2>
            <p class="subtitle">Choose your role to get started with the Student Progress Tracking System.</p>
        </div>

        <div class="button-container">
            <a href="register.php?type=student" class="user-type-btn">
                <i class="fas fa-user-graduate"></i>
                <span>Student</span>
                <p>Register as a student to track your progress</p>
            </a>
            <a href="register.php?type=supervisor" class="user-type-btn">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Supervisor</span>
                <p>Register as an academic supervisor</p>
            </a>
            <a href="register.php?type=sip" class="user-type-btn">
                <i class="fas fa-user-tie"></i>
                <span>SIP Supervisor</span>
                <p>Register as a SIP center supervisor</p>
            </a>
        </div>
        
        <div class="login-prompt">
            <p>Already have an account? <a href="login.php" class="login-link">Sign In</a></p>
        </div>
    </div>
</body>
</html>
