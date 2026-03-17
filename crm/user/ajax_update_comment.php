<?php
include_once __DIR__ .'/common/db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];

if (isset($_POST['add_comment'])) {
    $lead_id = mysqli_real_escape_string($conn, $_POST['lead_id']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $sub_status = isset($_POST['sub_status']) ? mysqli_real_escape_string($conn, $_POST['sub_status']) : null;
    $followup_date = !empty($_POST['followup_date']) ? mysqli_real_escape_string($conn, $_POST['followup_date']) : null;
    
    // Validate required fields
    if (empty($lead_id) || empty($comment) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit;
    }
    
    // Check if sub status is required
    $status_has_sub_status = false;
    $status_query = "SELECT * FROM status WHERE name = '$status' AND is_deleted = 0";
    $status_result = mysqli_query($conn, $status_query);
    if ($status_result && mysqli_num_rows($status_result) > 0) {
        $status_data = mysqli_fetch_assoc($status_result);
        $sub_status_query = "SELECT * FROM sub_status WHERE status_id = '{$status_data['id']}' AND is_deleted = 0";
        $sub_status_result = mysqli_query($conn, $sub_status_query);
        $status_has_sub_status = ($sub_status_result && mysqli_num_rows($sub_status_result) > 0);
    }
    
    if ($status_has_sub_status && empty($sub_status)) {
        echo json_encode(['success' => false, 'message' => 'Please select a sub status for this status.']);
        exit;
    }
    
    // Build update query
    $update_status = "UPDATE leads SET status = '$status', updated_at = NOW()";
    if ($sub_status) {
        $update_status .= ", sub_status = '$sub_status'";
    } else {
        $update_status .= ", sub_status = NULL";
    }
    $update_status .= " WHERE id = '$lead_id'";
    
    // Insert comment
    $insert_comment = "INSERT INTO comments (leadid, comments, datetime, created_by) 
                      VALUES ('$lead_id', '$comment', NOW(), '{$userId}')";
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update lead status
        if (!mysqli_query($conn, $update_status)) {
            throw new Exception("Error updating status: " . mysqli_error($conn));
        }
        
        // Insert comment
        if (!mysqli_query($conn, $insert_comment)) {
            throw new Exception("Error adding comment: " . mysqli_error($conn));
        }
        
        // If follow-up date is provided, update it
        if ($followup_date) {
            $update_followup = "UPDATE leads SET followup_date = '$followup_date' WHERE id = '$lead_id'";
            if (!mysqli_query($conn, $update_followup)) {
                throw new Exception("Error updating follow-up date: " . mysqli_error($conn));
            }
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        echo json_encode(['success' => true, 'message' => 'Status updated and comment added successfully!']);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>