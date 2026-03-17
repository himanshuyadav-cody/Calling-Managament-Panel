<?php
session_start();
unset($_SESSION['super_admin_user_id']);
unset($_SESSION['super_admin_logged_in']);
header('Location: login.php');
?>
