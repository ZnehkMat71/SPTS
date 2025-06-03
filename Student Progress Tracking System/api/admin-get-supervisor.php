<?php
require 'connect.php';

$course = $_GET['course'] ?? '';

if ($course) {
    $stmt = $conn->prepare("SELECT fullname FROM supervisors WHERE course = ?");
    $stmt->bind_param("s", $course);
    $stmt->execute();
    $stmt->bind_result($supervisor);
    $stmt->fetch();
    echo json_encode(['supervisor' => $supervisor]);
} else {
    echo json_encode(['supervisor' => '']);
}
