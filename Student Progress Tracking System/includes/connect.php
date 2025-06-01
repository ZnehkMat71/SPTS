<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "student_progress_db";

// Create a connection
$conn = mysqli_connect($host, $username, $password, $database);

// JSON-safe error output if used in API
if (!$conn) {
    if (str_contains($_SERVER['REQUEST_URI'], 'update-checklist.php')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    } else {
        die("Connection failed: " . mysqli_connect_error());
    }
    exit();
}
