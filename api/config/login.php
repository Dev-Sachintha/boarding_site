<?php
require_once '../config/db.php';

$data = json_decode(file_get_contents("php://input"));

if (empty($data->email) || empty($data->password)) {
    send_json_response(["success" => false, "message" => "Email and password are required."], 400);
}

$email = mysqli_real_escape_string($conn, trim($data->email));
$password = trim($data->password);

$sql = "SELECT id, name, email, password_hash, role FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($user = mysqli_fetch_assoc($result)) {
    if (password_verify($password, $user['password_hash'])) {
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
    send_json_response(["success" => false, "message" => "Invalid email or password."], 401);
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
