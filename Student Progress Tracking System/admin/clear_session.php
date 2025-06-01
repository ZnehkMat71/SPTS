<?php
session_start();

if (isset($_GET['type'])) {
    $type = $_GET['type'];
    if ($type === 'success' && isset($_SESSION['success'])) {
        unset($_SESSION['success']);
    } elseif ($type === 'error' && isset($_SESSION['error'])) {
        unset($_SESSION['error']);
    }
}

// Return a success response
http_response_code(200);
echo json_encode(['status' => 'success']);
?> 