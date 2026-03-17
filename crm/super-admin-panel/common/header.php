<?php
// Set the session timeout duration (5 hours = 28800 seconds)
$session_timeout = 28800;

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $session_timeout) {
    session_unset();     // Unset all session variables
    session_destroy();   // Destroy the session
    header("Location: login.php"); // Redirect to login page or desired page
    exit;
}

// Update the last activity time
$_SESSION['last_activity'] = time();
?>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <a class="navbar-brand" href="https://zapron.in/" target="_blank">Zapron</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ml-auto">
        <li class="nav-item">
          <a class="nav-link" href="logout.php">Logout</a>
        </li>
      </ul>
    </div>
  </nav>