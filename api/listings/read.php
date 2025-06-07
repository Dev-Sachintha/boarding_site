<?php
require_once '../config/db.php';

$sql = "SELECT l.*, u.name as landlord_name, u.email as landlord_email, 
                   AVG(r.rating) as average_rating, COUNT(r.id) as review_count
            FROM listings l
            JOIN users u ON l.user_id = u.id
            LEFT JOIN reviews r ON l.id = r.listing_id AND r.status = 'approved'
            WHERE 1=1"; // Start with a true condition for easy AND appending

// Filters
$params = [];
$types = "";

// Default: only show 'available' listings unless admin requests specific status or 'all'
if (isset($_GET['status']) && $_GET['status'] !== 'all' && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $sql .= " AND l.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
} elseif (!isset($_GET['status']) || (isset($_GET['status']) && $_GET['status'] !== 'all')) {
    $sql .= " AND l.status = 'available'"; // Default for public view
}
// If status=all, no status filter is added (admin sees all)


if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) { // For "My Listings"
    $sql .= " AND l.user_id = ?";
    $params[] = $_GET['user_id'];
    $types .= "i";
}

if (isset($_GET['search_term']) && !empty($_GET['search_term'])) {
    $term = "%" . $_GET['search_term'] . "%";
    $sql .= " AND (l.title LIKE ? OR l.description LIKE ? OR l.location LIKE ? OR l.city LIKE ?)";
    array_push($params, $term, $term, $term, $term);
    $types .= "ssss";
}
if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
    $sql .= " AND l.price >= ?";
    $params[] = $_GET['min_price'];
    $types .= "d";
}
if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
    $sql .= " AND l.price <= ?";
    $params[] = $_GET['max_price'];
    $types .= "d";
}
if (isset($_GET['gender']) && !empty($_GET['gender'])) {
    $sql .= " AND l.gender_preference = ?";
    $params[] = $_GET['gender'];
    $types .= "s";
}
if (isset($_GET['amenities']) && !empty($_GET['amenities'])) { // Simple: matches if amenity is IN the CSV string
    $amenity_term = "%" . $_GET['amenities'] . "%";
    $sql .= " AND l.amenities LIKE ?";
    $params[] = $amenity_term;
    $types .= "s";
}

$sql .= " GROUP BY l.id";

// Sorting
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'newest';
switch ($sort_by) {
    case 'price_asc':
        $sql .= " ORDER BY l.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY l.price DESC";
        break;
    case 'rating_desc':
        $sql .= " ORDER BY average_rating DESC, review_count DESC";
        break;
    default:
        $sql .= " ORDER BY l.created_at DESC"; // newest
}

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$listings = [];
while ($row = mysqli_fetch_assoc($result)) {
    $listings[] = $row;
}

send_json_response(["success" => true, "data" => $listings]);
mysqli_stmt_close($stmt);
mysqli_close($conn);
