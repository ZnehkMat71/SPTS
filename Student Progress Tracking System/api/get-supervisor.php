<?php
require_once 'connect.php';

// Initialize query and parameters
$query = "SELECT id, fullname FROM supervisors";
$params = [];
$types = "";

// Add course filter if provided
if (isset($_GET['course']) && !empty($_GET['course'])) {
    $query .= " WHERE course = ?";
    $params[] = $_GET['course'];
    $types .= "s";
}

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch results
$supervisors = [];
while ($row = $result->fetch_assoc()) {
    $supervisors[] = $row;
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($supervisors);
?>
