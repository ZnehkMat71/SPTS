<?php
require 'connect.php';

$center = $_GET['center'] ?? '';

if ($center) {
    $stmt = $conn->prepare("SELECT id, fullname FROM sip_supervisors WHERE sip_center = ?");
    $stmt->bind_param("s", $center);
    $stmt->execute();
    $stmt->bind_result($id, $fullname);
    $stmt->fetch();
    echo json_encode(['id' => $id, 'fullname' => $fullname]);
} else {
    echo json_encode(['id' => '', 'fullname' => '']);
}
