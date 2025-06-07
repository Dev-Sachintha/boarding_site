<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    send_json_response(["success" => false, "message" => "Unauthorized. Admins only."], 403);
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->id) || !is_numeric($data->id)) {
    send_json_response(["success" => false, "message" => "User ID is required."], 400);
}
$user_id_to_delete = intval($data->id);

if ($user_id_to_delete == $_SESSION['user_id']) {
    send_json_response(["success" => false, "message" => "Admins cannot delete their own account via this panel."], 400);
}

// Note: ON DELETE CASCADE in the DB for listings and reviews handles their deletion.
// If not, you'd need to delete associated listings/reviews first.
// Also, delete uploaded images for listings by this user before deleting the user.
// This is a simplified delete.

// Find and delete images for listings by this user
$sql_get_images = "SELECT primary_image_url FROM listings WHERE user_id = ?";
$stmt_get_images = mysqli_prepare($conn, $sql_get_images);
mysqli_stmt_bind_param($stmt_get_images, "i", $user_id_to_delete);
mysqli_stmt_execute($stmt_get_images);
$result_images = mysqli_stmt_get_result($stmt_get_images);
while ($row_image = mysqli_fetch_assoc($result_images)) {
    if (!empty($row_image['primary_image_url'])) {
        $image_server_path = str_replace('api/', '../', $row_image['primary_image_url']);
        if (file_exists($image_server_path)) {
            unlink($image_server_path);
        }
    }
}
mysqli_stmt_close($stmt_get_images);


$sql = "DELETE FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id_to_delete);

if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        send_json_response(["success" => true, "message" => "User and their associated data deleted successfully."]);
    } else {
        send_json_response(["success" => false, "message" => "User not found or already deleted."], 404);
    }
} else {
    send_json_response(["success" => false, "message" => "Failed to delete user: " . mysqli_error($conn)], 500);
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
