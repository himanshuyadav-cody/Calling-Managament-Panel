<?php
include_once __DIR__ .'/common/db_connection.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $reset_token = bin2hex(random_bytes(16)); // Generate a secure token

    // Save reset token in the database
    $sql = "UPDATE users SET reset_token = ? WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $reset_token, $email);

    if ($stmt->execute()) {
        // Send reset link via email
        $reset_link = "http://yourdomain.com/reset_password.php?token=$reset_token";
        mail($email, "Password Reset Request", "Click this link to reset your password: $reset_link");

        echo "<div class='alert alert-success'>Password reset link has been sent to your email.</div>";
    } else {
        echo "<div class='alert alert-danger'>Error processing request. Please try again later.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forget Password</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <!-- Header -->
    <?php include ('common/header.php'); ?>
    <div class="container-fluid">
        <div class="row">
        <!-- Sidebar -->
        <?php include ('common/sidebar.php'); ?>

            <div class="container d-flex justify-content-center align-items-center vh-100">
                <div class="card p-4" style="width: 100%; max-width: 400px;">
                <h2 class="text-center mb-4">Forget Password</h2>
                <div id="error-message" class="text-danger text-center"></div> 
                
                <form method="POST" action="">
                    <div class="form-group">
                    <label for="username">Enter your email address:</label>
                    <input type="text" id="username" name="current_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">forget Password</button>
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
