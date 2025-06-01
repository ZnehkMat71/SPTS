<?php
session_start();
ini_set('display_errors', 1); // Enable for debugging
error_reporting(E_ALL);

require '../includes/connect.php';  // Updated path to connect.php

// Always return JSON
header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Must be authenticated supervisor
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'supervisor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Read & decode JSON input
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

// Validate fields
if (
    !isset($data['student_id'], $data['document_name'], $data['is_checked']) ||
    !is_string($data['student_id']) ||
    !is_string($data['document_name'])
) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid fields']);
    exit;
}

$studentId    = trim($data['student_id']);
$documentName = trim($data['document_name']);
$isChecked    = filter_var($data['is_checked'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
$checkedBy    = (int) $_SESSION['user_id'];

try {
    if (!$conn->begin_transaction()) {
        throw new RuntimeException("Failed to begin transaction: " . $conn->error);
    }

    $stmt = $conn->prepare("
        INSERT INTO document_checklist (
            student_identifier,
            document_name,
            is_checked,
            checked_by,
            checked_at
        ) VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            is_checked = VALUES(is_checked),
            checked_by = VALUES(checked_by),
            checked_at = NOW()
    ");

    if (!$stmt) {
        throw new RuntimeException("Failed to prepare statement: " . $conn->error);
    }

    if (!$stmt->bind_param('ssii', $studentId, $documentName, $isChecked, $checkedBy)) {
        throw new RuntimeException("Failed to bind parameters: " . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new RuntimeException("Failed to execute statement: " . $stmt->error);
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'document' => [
            'student_id'    => $studentId,
            'document_name' => $documentName,
            'status'        => $isChecked ? 'verified' : 'unverified'
        ]
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }

    http_response_code(500);
    error_log("update-checklist error: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());

    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'debug'   => [
            'error' => $e->getMessage(),
            'code'  => $e->getCode()
        ]
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
} 