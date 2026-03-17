<?php
include_once __DIR__ .'/../common/constant.php';

function encryptPassword($password) {
    return openssl_encrypt($password, 'aes-128-cbc', ENCRYPTION_KEY, 0, '1234567890123456');
}

function decryptPassword($encryptedPassword) {
    return openssl_decrypt($encryptedPassword, 'aes-128-cbc', ENCRYPTION_KEY, 0, '1234567890123456');
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