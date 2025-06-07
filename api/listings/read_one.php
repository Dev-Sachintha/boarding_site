<?php
require_once '../config/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    send_json_response(["success" => false, "message" => "Listing ID is required and must be numeric."], 400);
}
$listing_id = intval($_GET['id']);

// Fetch listing details
$sql_listing = "SELECT l.*, u.name as landlord_name, u.email as landlord_email 
                    FROM listings l 
                    JOIN users u ON l.user_id = u.id
                    WHERE l.id = ? AND (l.status = 'available' OR l.status = 'pending' OR ?)"; // Admin can see pending

$is_admin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
$stmt_listing = mysqli_prepare($conn, $sql_listing);
mysqli_stmt_bind_param($stmt_listing, "ii", $listing_id, $is_admin);
mysqli_stmt_execute($stmt_listing);
$result_listing = mysqli_stmt_get_result($stmt_listing);
$listing = mysqli_fetch_assoc($result_listing);
mysqli_stmt_close($stmt_listing);

if (!$listing) {
    send_json_response(["success" => false, "message" => "Listing not found or not accessible."], 404);
}

// Fetch reviews for this listing
$sql_reviews = "SELECT r.*, u.name as user_name 
                    FROM reviews r 
                    JOIN users u ON r.user_id = u.id 
                    WHERE r.listing_id = ? AND r.status = 'approved'
                    ORDER BY r.created_at DESC";
$stmt_reviews = mysqli_prepare($conn, $sql_reviews);
mysqli_stmt_bind_param($stmt_reviews, "i", $listing_id);
mysqli_stmt_execute($stmt_reviews);
$result_reviews = mysqli_stmt_get_result($stmt_reviews);
$reviews = [];
while ($row = mysqli_fetch_assoc($result_reviews)) {
    $reviews[] = $row;
}
mysqli_stmt_close($stmt_reviews);

send_json_response(["success" => true, "data" => ["listing" => $listing, "reviews" => $reviews]]);
mysqli_close($conn);
