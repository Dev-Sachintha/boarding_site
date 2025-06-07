<?php
require_once '../config/db.php';

if (isset($_SESSION['user_id'])) {
    send_json_response([
        "success" => true,
        "data" => [
            "id" => $_SESSION['user_id'],
            "name" => $_SESSION['user_name'],
            "email" => $_SESSION['user_email'],
            "role" => $_SESSION['user_role']
        ]
    ]);
} else {
    send_json_response(["success" => false, "message" => "No active session."], 200); // 200 as it's not an error, just no session
}
