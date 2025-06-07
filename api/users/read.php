<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    send_json_response(["success" => false, "message" => "Unauthorized. Admins only."], 403);
}

$sql = "SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

send_json_response(["success" => true, "data" => $users]);
mysqli_close($conn);
