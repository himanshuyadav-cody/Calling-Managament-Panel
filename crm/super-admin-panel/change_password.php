<?php
include_once __DIR__ .'/common/db_connection.php';
include_once __DIR__ .'/common/utils.php';

if (!isset($_SESSION['super_admin_user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = getAdminUserId();
    $current_password = encryptPassword($_POST['current_password']);
    $new_password = encryptPassword($_POST['new_password']);

    // Verify current password
    $sql = "SELECT * FROM users WHERE id = ? AND password = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $current_password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update to new password
        $update_sql = "UPDATE users SET password = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_password, $user_id);

        if ($update_stmt->execute()) {
            echo "<div class='alert alert-success'>Password changed successfully.</div>";
        } else {
            echo "<div class='alert alert-danger'>Error updating password.</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>Current password is incorrect.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change Password</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <!-- Header -->
    <?php include('common/header.php'); ?>
    <div class="container-fluid">
        <div class="row">
        <!-- Sidebar -->
        <?php include('common/sidebar.php'); ?>

            <div class="container d-flex justify-content-center align-items-center vh-100">
                <div class="card p-4" style="width: 100%; max-width: 400px;">
                <h2 class="text-center mb-4">Change Password</h2>
                <div id="error-message" class="text-danger text-center"></div> 
                
                <form id="login-form" method="POST" action="">
                    <div class="form-group">
                    <label for="username">Current Password</label>
                    <input type="text" id="username" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="new_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Change Password</button>
                </form>
                </div>
            </div>
        </div>
    </div>    
  
    <!-- Footer -->
    <?php include('common/footer.php'); ?>
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
</body>
</html>