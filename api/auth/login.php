<?php
// These should be at the VERY TOP if you're adding them for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/db.php'; // This already defines send_json_response

$data = json_decode(file_get_contents("php://input"));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(["success" => false, "message" => "Invalid request method."], 405);
}

if (empty($data->email) || empty($data->password)) {
    send_json_response(["success" => false, "message" => "Email and password are required."], 400);
}

$email = trim($data->email); // No need to escape for prepared statements here
$password = trim($data->password);

// Validate email format on server side too
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_json_response(["success" => false, "message" => "Invalid email format."], 400);
}

$sql = "SELECT id, name, email, password_hash, role FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    send_json_response(["success" => false, "message" => "Database query preparation failed: " . mysqli_error($conn)], 500);
}

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($user = mysqli_fetch_assoc($result)) {
    if (password_verify($password, $user['password_hash'])) {
        // Regenerate session ID for security upon login
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];

        send_json_response([
            "success" => true,
            "message" => "Login successful.",
            "data" => ["id" => $user['id'], "name" => $user['name'], "email" => $user['email'], "role" => $user['role']]
        ]);
    } else {
        send_json_response(["success" => false, "message" => "Invalid email or password."], 401);
    }
} else {
    // To prevent user enumeration, give the same message as wrong password
    send_json_response(["success" => false, "message" => "Invalid email or password."], 401);
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
