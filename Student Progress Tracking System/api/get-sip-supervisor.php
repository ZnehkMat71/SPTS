<?php
require_once 'connect.php';

$query = "SELECT id, fullname FROM sip_supervisors";
$result = mysqli_query($conn, $query);

$sipSupervisors = [];
while ($row = mysqli_fetch_assoc($result)) {
    $sipSupervisors[] = $row;
}

header('Content-Type: application/json');
echo json_encode($sipSupervisors);
?>
