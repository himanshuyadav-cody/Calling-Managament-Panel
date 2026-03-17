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
   return (isset($_SESSION['role']) && $_SESSION['role'] === 0) ? $_SESSION['user_id'] : '';
}

function isStore() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 1) ? $_SESSION['store_user_id'] : '';
}

function isAgent() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 2) ? $_SESSION['user_id'] : '';
}

function getAdminUserId() {
    return $_SESSION['admin_user_id'] ?? null;
}

function getStoreId() {
    return $_SESSION['store_user_id'] ?? null;
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
