<?php
include_once __DIR__ .'/common/db_connection.php';
include_once __DIR__ .'/common/utils.php';

// Your wifi specific IP address
// $allowed_ips = ["103.69.13.250" , "106.215.92.209"];

// // Get the user's current IP address
// $current_ip = $_SERVER['REMOTE_ADDR'];

// // Check if the current IP matches your wifi IP
// if (!in_array($current_ip, $allowed_ips)) {
//     echo "Access denied: Unauthorized IP address.";
//     exit;
// }

$email = $_POST['email'];
$password = encryptPassword($_POST['password']);

$sql = "SELECT * FROM users WHERE email = ? AND password = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $email, $password);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['logged_in'] = true;

    echo "success";
} else {
    echo "Invalid login credentials.";
}

$stmt->close();
$conn->close();
?>