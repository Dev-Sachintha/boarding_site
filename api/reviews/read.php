<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    send_json_response(["success" => false, "message" => "Unauthorized. Admins only."], 403);
}

$sql = "SELECT r.*, u.name as user_name, l.title as listing_title 
            FROM reviews r
            JOIN users u ON r.user_id = u.id
            JOIN listings l ON r.listing_id = l.id
            WHERE 1=1";

$params = [];
$types = "";

if (isset($_GET['status']) && $_GET['status'] !== 'all') {
    $sql .= " AND r.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}
// Add more filters if needed (e.g., by listing_id, user_id)

$sql .= " ORDER BY r.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$reviews = [];
while ($row = mysqli_fetch_assoc($result)) {
    $reviews[] = $row;
}

send_json_response(["success" => true, "data" => $reviews]);
mysqli_stmt_close($stmt);
mysqli_close($conn);
