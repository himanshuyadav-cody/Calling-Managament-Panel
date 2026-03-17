<?php
$host = "localhost";
$db = "";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Helper functions for role-based access control
session_start();

function isSuperAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin';
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isAgent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'agent';
}

function getAdminUserId() {
    return $_SESSION['admin_user_id'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getConn() {
    global $conn; // Make the global $conn variable accessible inside this function
    if (!$conn) {
        global $host, $user, $pass, $db;
        $conn = new mysqli($host, $user, $pass, $db);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
    }
    return $conn;
}
?>
