<?php
require_once 'connect.php';

$query = "SELECT id, fullname FROM supervisors";
$result = mysqli_query($conn, $query);

$supervisors = [];
while ($row = mysqli_fetch_assoc($result)) {
    $supervisors[] = $row;
}

header('Content-Type: application/json');
echo json_encode($supervisors);
?>
