<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();
require 'connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

try {
    // Prepare the SQL query to fetch supervisor details
    $stmt = $conn->prepare("
        SELECT 
            id,
            fullname,
            username,
            course
        FROM supervisors
        ORDER BY fullname ASC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetch all supervisors into an array
    $supervisors = [];
    while ($row = $result->fetch_assoc()) {
        $supervisors[] = [
            'id' => $row['id'],
            'fullname' => $row['fullname'],
            'username' => $row['username'],
            'course' => $row['course']
        ];
    }
    
    // Return the data as JSON
    header('Content-Type: application/json');
    echo json_encode($supervisors);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 