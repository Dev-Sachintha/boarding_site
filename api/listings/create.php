<?php
// --- STRICT TYPING AND ERROR HANDLING AT THE VERY TOP ---
declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// --- CONFIG AND HELPER ---
// db.php defines send_json_response and starts the session, also handles DB connection.
// It should also have error reporting and CORS headers.
require_once '../config/db.php';

// --- AUTHORIZATION ---
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'landlord' && $_SESSION['user_role'] !== 'admin')) {
    send_json_response(["success" => false, "message" => "Unauthorized. Only landlords or admins can create listings."], 403);
}

// --- VALIDATE REQUEST METHOD ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(["success" => false, "message" => "Invalid request method. Only POST is allowed."], 405);
}

// --- VALIDATE POST DATA (EXPECTED FROM FormData) ---
$required_fields = ['title', 'location', 'city', 'price', 'gender_preference', 'description'];
$missing_fields = [];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $missing_fields[] = $field;
    }
}
if (!empty($missing_fields)) {
    send_json_response(["success" => false, "message" => "Missing required fields: " . implode(', ', $missing_fields) . "."], 400);
}

// --- SANITIZE AND ASSIGN INPUTS ---
$user_id = (int)$_SESSION['user_id'];
$title = trim((string)$_POST['title']);
$location = trim((string)$_POST['location']);
$city = trim((string)$_POST['city']);
$price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
$gender_preference = trim((string)$_POST['gender_preference']);
$description = trim((string)$_POST['description']);
$map_link_or_embed = isset($_POST['map_link_or_embed']) ? trim((string)$_POST['map_link_or_embed']) : null;

// Further validation for critical fields
if ($price === false || $price < 0) {
    send_json_response(["success" => false, "message" => "Invalid price provided. Must be a non-negative number."], 400);
}
if (empty($title) || strlen($title) > 255) {
    send_json_response(["success" => false, "message" => "Title is required and must be less than 255 characters."], 400);
}
// Add more specific validations for location, city, description, gender_preference if needed

if (empty($map_link_or_embed)) { // Ensure empty string becomes NULL for DB
    $map_link_or_embed = null;
}

$amenities_array = isset($_POST['amenities']) && is_array($_POST['amenities']) ? $_POST['amenities'] : [];
$sanitized_amenities = [];
foreach ($amenities_array as $amenity) {
    if (!empty(trim((string)$amenity))) { // Only add non-empty amenities
        $sanitized_amenities[] = htmlspecialchars(trim((string)$amenity), ENT_QUOTES, 'UTF-8');
    }
}
$amenities_csv = implode(',', $sanitized_amenities);


// --- FILE UPLOAD LOGIC ---
$uploaded_image_server_paths = [];
$uploaded_image_web_paths = [];
$primary_image_url = null;

$project_root_guess = dirname(__DIR__, 2); // Should be .../boarding_site/
$upload_dir_server_base = $project_root_guess . '/api/uploads/listings/';
$web_accessible_upload_base = 'api/uploads/listings/';

$allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
$max_file_size_per_file = 2 * 1024 * 1024; // 2MB per file
$max_files_to_upload = 5;

// Ensure upload directory exists and is writable
if (!is_dir($upload_dir_server_base)) {
    if (!@mkdir($upload_dir_server_base, 0775, true) && !is_dir($upload_dir_server_base)) { // Suppress error for mkdir, then check
        error_log("Failed to create upload directory: " . $upload_dir_server_base . " - Check parent directory permissions.");
        send_json_response(["success" => false, "message" => "Upload directory setup error (creation failed). Please contact support."], 500);
    }
}
if (!is_writable($upload_dir_server_base)) {
    error_log("Upload directory not writable: " . $upload_dir_server_base);
    send_json_response(["success" => false, "message" => "Upload directory permission error. Please contact support."], 500);
}

if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
    $num_files = count($_FILES['images']['name']);

    if ($num_files > $max_files_to_upload) {
        send_json_response(["success" => false, "message" => "Too many files. Maximum is {$max_files_to_upload}."], 400);
    }

    for ($i = 0; $i < $num_files; $i++) {
        // Check if a file was actually uploaded for this array entry
        if ($_FILES['images']['error'][$i] == UPLOAD_ERR_NO_FILE) {
            continue; // Skip if no file was provided for this slot
        }
        if ($_FILES['images']['error'][$i] == UPLOAD_ERR_OK) {
            if (!in_array($_FILES['images']['type'][$i], $allowed_types)) {
                error_log("Invalid file type uploaded: " . $_FILES['images']['name'][$i] . " (Type: " . $_FILES['images']['type'][$i] . ")");
                // Optionally, you could collect these errors and send them back to the user
                continue;
            }
            if ($_FILES['images']['size'][$i] > $max_file_size_per_file) {
                error_log("File too large: " . $_FILES['images']['name'][$i] . " (Size: " . $_FILES['images']['size'][$i] . ")");
                continue;
            }

            $file_original_name = $_FILES['images']['name'][$i];
            $file_tmp_name = $_FILES['images']['tmp_name'][$i];
            $file_ext = strtolower(pathinfo($file_original_name, PATHINFO_EXTENSION));

            // Sanitize filename to prevent directory traversal or other issues
            $safe_basename = preg_replace("/[^a-zA-Z0-9\._-]/", "", basename($file_original_name, "." . $file_ext));
            if (empty($safe_basename)) $safe_basename = "image"; // fallback if name becomes empty

            $unique_filename = uniqid($safe_basename . '_', true) . '.' . $file_ext;
            $upload_path_server = $upload_dir_server_base . $unique_filename;

            if (move_uploaded_file($file_tmp_name, $upload_path_server)) {
                $uploaded_image_server_paths[] = $upload_path_server;
                $uploaded_image_web_paths[] = $web_accessible_upload_base . $unique_filename;
            } else {
                error_log("Failed to move uploaded file: " . $file_original_name . ". Target: " . $upload_path_server . ". PHP Upload Error: " . $_FILES['images']['error'][$i]);
            }
        } elseif ($_FILES['images']['error'][$i] != UPLOAD_ERR_NO_FILE) {
            error_log("Upload error for file " . $_FILES['images']['name'][$i] . ": " . $_FILES['images']['error'][$i]);
        }
    }
}

$primary_image_url = !empty($uploaded_image_web_paths) ? $uploaded_image_web_paths[0] : null;

// --- DATABASE INSERTION ---
$status = ($_SESSION['user_role'] === 'admin') ? 'available' : 'pending';

// Values for binding (already sanitized or validated)
$title_db = $title;
$description_db = $description;
$location_db = $location;
$city_db = $city;
$gender_preference_db = $gender_preference;

$sql = "INSERT INTO listings (user_id, title, description, location, city, price, gender_preference, amenities, primary_image_url, map_link_or_embed, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    error_log("DB Prepare Error in create.php: " . mysqli_error($conn));
    foreach ($uploaded_image_server_paths as $path_to_delete) {
        if (file_exists($path_to_delete)) @unlink($path_to_delete);
    }
    send_json_response(["success" => false, "message" => "Database error (prepare failed). Please contact support."], 500);
}

mysqli_stmt_bind_param(
    $stmt,
    "issssdsssss",
    $user_id,
    $title_db,
    $description_db,
    $location_db,
    $city_db,
    $price,
    $gender_preference_db,
    $amenities_csv,
    $primary_image_url,
    $map_link_or_embed,
    $status
);

if (mysqli_stmt_execute($stmt)) {
    $listing_id = mysqli_insert_id($conn);

    // If storing multiple images in a separate `listing_images` table:
    // if ($listing_id && count($uploaded_image_web_paths) > 0) {
    //     $sql_img = "INSERT INTO listing_images (listing_id, image_url, is_primary) VALUES (?, ?, ?)";
    //     foreach ($uploaded_image_web_paths as $index => $image_web_path) {
    //         $is_primary_flag = ($image_web_path === $primary_image_url) ? 1 : 0; 
    //         $stmt_img = mysqli_prepare($conn, $sql_img);
    //         if ($stmt_img) {
    //             mysqli_stmt_bind_param($stmt_img, "isi", $listing_id, $image_web_path, $is_primary_flag);
    //             mysqli_stmt_execute($stmt_img);
    //             mysqli_stmt_close($stmt_img);
    //         } else {
    //             error_log("DB Prepare Error for listing_images: " . mysqli_error($conn));
    //         }
    //     }
    // }

    send_json_response(["success" => true, "message" => "Listing created successfully. Status: {$status}", "listingId" => $listing_id], 201);
} else {
    error_log("DB Execute Error in create.php: " . mysqli_stmt_error($stmt) . " | SQL Error: " . mysqli_error($conn));
    foreach ($uploaded_image_server_paths as $path_to_delete) {
        if (file_exists($path_to_delete)) @unlink($path_to_delete);
    }
    send_json_response(["success" => false, "message" => "Failed to save listing to database. Please contact support."], 500);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

// No closing PHP tag 
