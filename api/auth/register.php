<?php
require_once '../config/db.php';

$data = json_decode(file_get_contents("php://input"));

if (empty($data->name) || empty($data->email) || empty($data->password) || empty($data->role)) {
    send_json_response(["success" => false, "message" => "All fields are required."], 400);
}

$name = mysqli_real_escape_string($conn, trim($data->name));
$email = mysqli_real_escape_string($conn, trim($data->email));
$password = trim($data->password);
$role = mysqli_real_escape_string($conn, trim($data->role));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_json_response(["success" => false, "message" => "Invalid email format."], 400);
}
if (strlen($password) < 6) {
    send_json_response(["success" => false, "message" => "Password must be at least 6 characters long."], 400);
}
if (!in_array($role, ['student', 'landlord'])) { // Admin creation should be separate
    send_json_response(["success" => false, "message" => "Invalid user role."], 400);
}

// Check if email already exists
$sql_check = "SELECT id FROM users WHERE email = ?";
$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, "s", $email);
mysqli_stmt_execute($stmt_check);
mysqli_stmt_store_result($stmt_check);

if (mysqli_stmt_num_rows($stmt_check) > 0) {
    send_json_response(["success" => false, "message" => "Email already registered."], 409); // 409 Conflict
}
mysqli_stmt_close($stmt_check);

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $password_hash, $role);

if (mysqli_stmt_execute($stmt)) {
    $user_id = mysqli_insert_id($conn);
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role'] = $role;

    send_json_response([
        "success" => true,
        "message" => "Registration successful.",
        "data" => ["id" => $user_id, "name" => $name, "email" => $email, "role" => $role]
    ], 201);
} else {
    send_json_response(["success" => false, "message" => "Registration failed. Please try again later. " . mysqli_error($conn)], 500);
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
