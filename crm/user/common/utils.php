<?php
include_once __DIR__ .'/../common/constant.php';


function encryptPassword($password) {
    return openssl_encrypt($password, 'aes-128-cbc', ENCRYPTION_KEY, 0, '1234567890123456');
}

function decryptPassword($encryptedPassword) {
    return openssl_decrypt($encryptedPassword, 'aes-128-cbc', ENCRYPTION_KEY, 0, '1234567890123456');
}

// function encryptPassword($password) {
//     return password_hash($password, PASSWORD_DEFAULT);
// }

// function verifyPassword($password, $hash) {
//     return password_verify($password, $hash);
// }

function getRoleText($role) {
    switch($role) {
        case 0: return 'Super Admin';
        case 1: return 'Admin';
        case 2: return 'Customer';
        default: return 'Unknown';
    }
}

// File upload function
function uploadFile($file, $uploadDir = 'uploads/') {
    // Create directory if not exists
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Generate unique filename
    $fileName = time() . '_' . uniqid() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;
    
    // Check if file upload is successful
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $fileName;
    }
    return null;
}

// Delete file function
function deleteFile($filePath) {
    if (file_exists($filePath) && is_file($filePath)) {
        unlink($filePath);
        return true;
    }
    return false;
}

// Time conversion functions
function formatTime24To12($time24) {
    if (empty($time24) || $time24 == '00:00:00') return '';
    
    try {
        $time = DateTime::createFromFormat('H:i:s', $time24);
        if ($time === false) {
            $time = DateTime::createFromFormat('H:i', $time24);
        }
        if ($time === false) {
            return $time24;
        }
        return $time->format('h:i A');
    } catch (Exception $e) {
        return $time24;
    }
}

function formatTime12To24($time12) {
    if (empty($time12)) return '';
    
    try {
        $time = DateTime::createFromFormat('h:i A', $time12);
        if ($time === false) {
            return $time12;
        }
        return $time->format('H:i:s');
    } catch (Exception $e) {
        return $time12;
    }
}

function getAMPMFromTime($time) {
    if (empty($time) || $time == '00:00:00') return 'AM';
    
    try {
        $timeObj = DateTime::createFromFormat('H:i:s', $time);
        if ($timeObj === false) {
            $timeObj = DateTime::createFromFormat('H:i', $time);
        }
        if ($timeObj === false) {
            return 'AM';
        }
        return $timeObj->format('A');
    } catch (Exception $e) {
        return 'AM';
    }
}

function removeCacheFile($fileName) {
    $cacheDir = __DIR__ . '/../../cache';     
    $fileToDelete = $cacheDir . '/' . $fileName. '.html';
    if (file_exists($fileToDelete)) {
        if (unlink($fileToDelete)) {
           return true;
        }
    }

    return false;
}
?>