<?php
require '../includes/connect.php';

$identifier = 'admin';
$fullname = 'Administrator';
$password = 'admin';
$role = 'admin';

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// STEP 1: Check if prepare() worked
$check = $conn->prepare("SELECT id FROM users WHERE username = ?");
if (!$check) {
    die("❌ SELECT prepare failed: " . $conn->error);
}
$check->bind_param("s", $identifier);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo "Admin account already exists.";
} else {
    // STEP 2: Check if insert prepare works
    $stmt = $conn->prepare("INSERT INTO users (fullname, password, role, username) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        die("❌ INSERT prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssss", $fullname, $hashed_password, $role, $identifier);

    if ($stmt->execute()) {
        echo "✅ Admin account created successfully.";
    } else {
        echo "❌ Insert error: " . $stmt->error;
    }

    $stmt->close();
}

$check->close();
$conn->close();
?>
