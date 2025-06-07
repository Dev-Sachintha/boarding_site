<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    send_json_response(["success" => false, "message" => "Unauthorized. Admins only."], 403);
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->id) || !is_numeric($data->id) || empty($data->status)) {
    send_json_response(["success" => false, "message" => "Review ID and new status are required."], 400);
}
$review_id = intval($data->id);
$new_status = mysqli_real_escape_string($conn, $data->status);

$allowed_statuses = ['pending', 'approved', 'rejected'];
if (!in_array($new_status, $allowed_statuses)) {
    send_json_response(["success" => false, "message" => "Invalid status provided."], 400);
}

$sql = "UPDATE reviews SET status = ? WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $new_status, $review_id);

if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        send_json_response(["success" => true, "message" => "Review status updated successfully to '{$new_status}'."]);
    } else {
        send_json_response(["success" => false, "message" => "Review not found or status already set."], 404);
    }
} else {
    send_json_response(["success" => false, "message" => "Failed to update review status: " . mysqli_error($conn)], 500);
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
