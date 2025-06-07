<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    send_json_response(["success" => false, "message" => "Unauthorized. Please log in."], 403);
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->id) || !is_numeric($data->id)) {
    send_json_response(["success" => false, "message" => "Listing ID is required."], 400);
}
$listing_id = intval($data->id);
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];

// Check ownership or admin role
$sql_check = "SELECT user_id, primary_image_url FROM listings WHERE id = ?";
$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, "i", $listing_id);
mysqli_stmt_execute($stmt_check);
$result_check = mysqli_stmt_get_result($stmt_check);
$listing_data = mysqli_fetch_assoc($result_check);
mysqli_stmt_close($stmt_check);

if (!$listing_data) {
    send_json_response(["success" => false, "message" => "Listing not found."], 404);
}

if ($current_user_role !== 'admin' && $listing_data['user_id'] != $current_user_id) {
    send_json_response(["success" => false, "message" => "You are not authorized to delete this listing."], 403);
}

// Delete the image file if it exists
if (!empty($listing_data['primary_image_url'])) {
    // Convert web path to server path. Assuming 'api/' is part of the web path.
    $image_server_path = str_replace('api/', '../', $listing_data['primary_image_url']);
    if (file_exists($image_server_path)) {
        unlink($image_server_path);
    }
}

// Delete reviews associated with the listing first (or set ON DELETE CASCADE in DB)
$sql_delete_reviews = "DELETE FROM reviews WHERE listing_id = ?";
$stmt_delete_reviews = mysqli_prepare($conn, $sql_delete_reviews);
mysqli_stmt_bind_param($stmt_delete_reviews, "i", $listing_id);
mysqli_stmt_execute($stmt_delete_reviews); // Ignore errors for now, main goal is listing deletion
mysqli_stmt_close($stmt_delete_reviews);


$sql = "DELETE FROM listings WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $listing_id);

if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        send_json_response(["success" => true, "message" => "Listing deleted successfully."]);
    } else {
        send_json_response(["success" => false, "message" => "Listing not found or already deleted."], 404);
    }
} else {
    send_json_response(["success" => false, "message" => "Failed to delete listing: " . mysqli_error($conn)], 500);
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
