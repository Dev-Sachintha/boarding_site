<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    send_json_response(["success" => false, "message" => "Unauthorized. Please log in to submit a review."], 403);
}

$data = json_decode(file_get_contents("php://input"));

if (
    empty($data->listing_id) || !is_numeric($data->listing_id) ||
    empty($data->rating) || !is_numeric($data->rating) || $data->rating < 1 || $data->rating > 5 ||
    empty($data->comment)
) {
    send_json_response(["success" => false, "message" => "Listing ID, valid rating (1-5), and comment are required."], 400);
}

$listing_id = intval($data->listing_id);
$user_id = $_SESSION['user_id']; // Use session user_id
$rating = intval($data->rating);
$comment = mysqli_real_escape_string($conn, trim($data->comment));
$status = 'pending'; // Reviews usually need approval

// Optional: Check if user has already reviewed this listing (allow only one review per user per listing)
// $sql_check = "SELECT id FROM reviews WHERE user_id = ? AND listing_id = ?"; ...

$sql = "INSERT INTO reviews (listing_id, user_id, rating, comment, status) VALUES (?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iiiss", $listing_id, $user_id, $rating, $comment, $status);

if (mysqli_stmt_execute($stmt)) {
    send_json_response(["success" => true, "message" => "Review submitted successfully. It is pending approval."], 201);
} else {
    send_json_response(["success" => false, "message" => "Failed to submit review: " . mysqli_error($conn)], 500);
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
