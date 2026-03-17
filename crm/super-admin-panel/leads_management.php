<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ .'/common/db_connection.php';
include_once __DIR__ .'/common/utils.php';

if (!isset($_SESSION['super_admin_user_id'])) {
    header("Location: login.php");
    exit;
}

// NEW: Handle Bulk Assign by Lead IDs
if (isset($_POST['bulk_assign_by_ids'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['assign_user_id']);
    $lead_ids_input = $_POST['lead_ids_input'] ?? '';
    
    if (empty($user_id)) {
        $_SESSION['error'] = "Please select a user to assign leads.";
        header("Location: leads_management.php");
        exit;
    }
    
    if (empty($lead_ids_input)) {
        $_SESSION['error'] = "Please enter lead IDs to assign.";
        header("Location: leads_management.php");
        exit;
    }
    
    // Parse lead IDs from input (comma, space, or newline separated)
    $lead_ids = preg_split('/[\s,\n]+/', $lead_ids_input, -1, PREG_SPLIT_NO_EMPTY);
    
    if (empty($lead_ids)) {
        $_SESSION['error'] = "No valid lead IDs found in the input.";
        header("Location: leads_management.php");
        exit;
    }
    
    // Clean and validate lead IDs
    $valid_lead_ids = [];
    foreach ($lead_ids as $lead_id) {
        $clean_lead_id = mysqli_real_escape_string($conn, trim($lead_id));
        if (!empty($clean_lead_id) && is_numeric($clean_lead_id)) {
            $valid_lead_ids[] = $clean_lead_id;
        }
    }
    
    if (empty($valid_lead_ids)) {
        $_SESSION['error'] = "No valid numeric lead IDs found.";
        header("Location: leads_management.php");
        exit;
    }
    
    $successCount = 0;
    $errorCount = 0;
    $notFoundCount = 0;
    
    foreach ($valid_lead_ids as $lead_id) {
        // Check if lead exists
        $check_lead_query = "SELECT id, assigned_to FROM leads WHERE id = '$lead_id' AND is_deleted = 0";
        $check_lead_result = mysqli_query($conn, $check_lead_query);
        
        if (!$check_lead_result || mysqli_num_rows($check_lead_result) == 0) {
            $notFoundCount++;
            continue;
        }
        
        $current_assigned = mysqli_fetch_assoc($check_lead_result);
        $previous_user_id = $current_assigned['assigned_to'];
        
        // Update lead assignment
        $update_query = "UPDATE leads SET assigned_to = '$user_id', updated_at = NOW() WHERE id = '$lead_id'";
        
        if (mysqli_query($conn, $update_query)) {
            $successCount++;
            
            // Record in assignment history
            $history_query = "INSERT INTO lead_assignment_history (lead_id, previous_user_id, new_user_id, assigned_by, assigned_at) 
                             VALUES ('$lead_id', " . ($previous_user_id ? "'$previous_user_id'" : "NULL") . ", '$user_id', '{$_SESSION['super_admin_user_id']}', NOW())";
            mysqli_query($conn, $history_query);
            
        } else {
            $errorCount++;
        }
    }
    
    // Prepare result message
    $message = "Bulk assignment completed: ";
    $messageParts = [];
    
    if ($successCount > 0) {
        $messageParts[] = "$successCount leads assigned successfully";
    }
    if ($errorCount > 0) {
        $messageParts[] = "$errorCount leads failed to assign";
    }
    if ($notFoundCount > 0) {
        $messageParts[] = "$notFoundCount lead IDs not found";
    }
    
    $_SESSION['message'] = $message . implode(", ", $messageParts);
    header("Location: leads_management.php");
    exit;
}

// Handle Comment Delete
if (isset($_POST['delete_comment'])) {
    $comment_id = mysqli_real_escape_string($conn, $_POST['comment_id']);
    $delete_reason = mysqli_real_escape_string($conn, $_POST['delete_reason']);
    
    if (empty($delete_reason)) {
        $_SESSION['error'] = "Please provide a reason for deletion.";
        header("Location: leads_management.php");
        exit;
    }
    
    // Check if comments table has soft delete columns
    $check_columns_query = "SHOW COLUMNS FROM comments LIKE 'is_deleted'";
    $check_columns_result = mysqli_query($conn, $check_columns_query);
    $has_soft_delete = (mysqli_num_rows($check_columns_result) > 0);
    
    // Pehle comment details fetch karo
    $comment_details_query = "SELECT c.*, u.name as user_name, l.id as lead_id 
                             FROM comments c 
                             LEFT JOIN users u ON c.created_by = u.id 
                             LEFT JOIN leads l ON c.leadid = l.id 
                             WHERE c.id = '$comment_id'";
    $comment_details_result = mysqli_query($conn, $comment_details_query);
    
    if (!$comment_details_result || mysqli_num_rows($comment_details_result) == 0) {
        $_SESSION['error'] = "Comment not found.";
        header("Location: leads_management.php");
        exit;
    }
    
    $comment_details = mysqli_fetch_assoc($comment_details_result);
    
    mysqli_begin_transaction($conn);
    
    try {
        if ($has_soft_delete) {
            // Soft delete the comment
            $delete_query = "UPDATE comments SET is_deleted = 1, deleted_at = NOW(), deleted_by = '{$_SESSION['super_admin_user_id']}' WHERE id = '$comment_id'";
        } else {
            // Hard delete the comment
            $delete_query = "DELETE FROM comments WHERE id = '$comment_id'";
        }
        
        if (!mysqli_query($conn, $delete_query)) {
            throw new Exception("Error deleting comment: " . mysqli_error($conn));
        }
        
        // Check if comment_delete_history table exists
        $check_history_table = "SHOW TABLES LIKE 'comment_delete_history'";
        $history_table_result = mysqli_query($conn, $check_history_table);
        
        if (mysqli_num_rows($history_table_result) > 0) {
            // Delete history mein record add karo
            $history_query = "INSERT INTO comment_delete_history 
                             (comment_id, lead_id, comment_text, original_comment_by, original_comment_date, deleted_by, deleted_at, delete_reason) 
                             VALUES (
                                 '$comment_id',
                                 '{$comment_details['lead_id']}',
                                 '" . mysqli_real_escape_string($conn, $comment_details['comments']) . "',
                                 '" . mysqli_real_escape_string($conn, $comment_details['user_name'] ?: 'System') . "',
                                 '" . $comment_details['datetime'] . "',
                                 '{$_SESSION['super_admin_user_id']}',
                                 NOW(),
                                 '$delete_reason'
                             )";
            
            if (!mysqli_query($conn, $history_query)) {
                throw new Exception("Error saving delete history: " . mysqli_error($conn));
            }
        }
        
        mysqli_commit($conn);
        $_SESSION['message'] = "Comment deleted successfully!";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: leads_management.php");
    exit;
}

// Handle Bulk Comment Delete
if (isset($_POST['bulk_delete_comments'])) {
    $comment_ids = $_POST['comment_ids'] ?? [];
    $bulk_delete_reason = mysqli_real_escape_string($conn, $_POST['bulk_delete_reason']);
    
    if (empty($comment_ids)) {
        $_SESSION['error'] = "Please select at least one comment to delete.";
        header("Location: leads_management.php");
        exit;
    }
    
    if (empty($bulk_delete_reason)) {
        $_SESSION['error'] = "Please provide a reason for deletion.";
        header("Location: leads_management.php");
        exit;
    }
    
    // Check if comments table has soft delete columns
    $check_columns_query = "SHOW COLUMNS FROM comments LIKE 'is_deleted'";
    $check_columns_result = mysqli_query($conn, $check_columns_query);
    $has_soft_delete = (mysqli_num_rows($check_columns_result) > 0);
    
    // Check if comment_delete_history table exists
    $check_history_table = "SHOW TABLES LIKE 'comment_delete_history'";
    $history_table_result = mysqli_query($conn, $check_history_table);
    $has_history_table = (mysqli_num_rows($history_table_result) > 0);
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($comment_ids as $comment_id) {
        $comment_id = mysqli_real_escape_string($conn, $comment_id);
        
        // Pehle comment details fetch karo
        $comment_details_query = "SELECT c.*, u.name as user_name, l.id as lead_id 
                                 FROM comments c 
                                 LEFT JOIN users u ON c.created_by = u.id 
                                 LEFT JOIN leads l ON c.leadid = l.id 
                                 WHERE c.id = '$comment_id'";
        $comment_details_result = mysqli_query($conn, $comment_details_query);
        
        if (!$comment_details_result || mysqli_num_rows($comment_details_result) == 0) {
            $errorCount++;
            continue;
        }
        
        $comment_details = mysqli_fetch_assoc($comment_details_result);
        
        mysqli_begin_transaction($conn);
        
        try {
            if ($has_soft_delete) {
                // Soft delete the comment
                $delete_query = "UPDATE comments SET is_deleted = 1, deleted_at = NOW(), deleted_by = '{$_SESSION['super_admin_user_id']}' WHERE id = '$comment_id'";
            } else {
                // Hard delete the comment
                $delete_query = "DELETE FROM comments WHERE id = '$comment_id'";
            }
            
            if (!mysqli_query($conn, $delete_query)) {
                throw new Exception("Error deleting comment: " . mysqli_error($conn));
            }
            
            if ($has_history_table) {
                // Delete history mein record add karo
                $history_query = "INSERT INTO comment_delete_history 
                                 (comment_id, lead_id, comment_text, original_comment_by, original_comment_date, deleted_by, deleted_at, delete_reason) 
                                 VALUES (
                                     '$comment_id',
                                     '{$comment_details['lead_id']}',
                                     '" . mysqli_real_escape_string($conn, $comment_details['comments']) . "',
                                     '" . mysqli_real_escape_string($conn, $comment_details['user_name'] ?: 'System') . "',
                                     '" . $comment_details['datetime'] . "',
                                     '{$_SESSION['super_admin_user_id']}',
                                     NOW(),
                                     '$bulk_delete_reason'
                                 )";
                
                if (!mysqli_query($conn, $history_query)) {
                    throw new Exception("Error saving delete history: " . mysqli_error($conn));
                }
            }
            
            mysqli_commit($conn);
            $successCount++;
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errorCount++;
        }
    }
    
    if ($errorCount == 0) {
        $_SESSION['message'] = "Successfully deleted $successCount comments!";
    } else {
        $_SESSION['message'] = "Deleted $successCount comments, $errorCount failed.";
    }
    
    header("Location: leads_management.php");
    exit;
}

// View Comment Delete History
if (isset($_GET['view_delete_history'])) {
    $lead_id = mysqli_real_escape_string($conn, $_GET['lead_id']);
    
    // Check if comment_delete_history table exists
    $check_history_table = "SHOW TABLES LIKE 'comment_delete_history'";
    $history_table_result = mysqli_query($conn, $check_history_table);
    
    if (mysqli_num_rows($history_table_result) > 0) {
        $history_query = "SELECT cdh.*, u.name as deleted_by_name 
                         FROM comment_delete_history cdh 
                         LEFT JOIN users u ON cdh.deleted_by = u.id 
                         WHERE cdh.lead_id = '$lead_id' 
                         ORDER BY cdh.deleted_at DESC";
        $history_result = mysqli_query($conn, $history_query);
        
        if ($history_result && mysqli_num_rows($history_result) > 0) {
            echo '<div class="delete-history-section">';
            echo '<h6 class="text-danger mb-3"><i class="fas fa-history me-2"></i>Comment Delete History</h6>';
            echo '<div class="table-responsive">';
            echo '<table class="table table-sm table-bordered">';
            echo '<thead class="table-danger">';
            echo '<tr>';
            echo '<th>#</th>';
            echo '<th>Original Comment</th>';
            echo '<th>Original By</th>';
            echo '<th>Original Date</th>';
            echo '<th>Deleted By</th>';
            echo '<th>Deleted At</th>';
            echo '<th>Delete Reason</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            $history_sno = 1;
            while ($history = mysqli_fetch_assoc($history_result)) {
                echo '<tr>';
                echo '<td>' . $history_sno++ . '</td>';
                echo '<td><small>' . htmlspecialchars($history['comment_text']) . '</small></td>';
                echo '<td><span class="badge bg-secondary">' . htmlspecialchars($history['original_comment_by']) . '</span></td>';
                echo '<td><small>' . date('M j, Y g:i A', strtotime($history['original_comment_date'])) . '</small></td>';
                echo '<td><span class="badge bg-danger">' . htmlspecialchars($history['deleted_by_name'] ?: 'System') . '</span></td>';
                echo '<td><small>' . date('M j, Y g:i A', strtotime($history['deleted_at'])) . '</small></td>';
                echo '<td><small class="text-muted">' . htmlspecialchars($history['delete_reason']) . '</small></td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="alert alert-info">No comment delete history found.</div>';
        }
    } else {
        echo '<div class="alert alert-warning">Delete history tracking is not available.</div>';
    }
    exit;
}

// Handle Export Leads to CSV - UPDATED VERSION
if (isset($_POST['export_leads'])) {
    // Get filter values for export - COMMENT DATE BASED
    $export_start_date = $_POST['export_start_date'] ?? '';
    $export_end_date = $_POST['export_end_date'] ?? '';
    $export_assigned_user = $_POST['export_assigned_user'] ?? '';
    $export_lead_status = $_POST['export_lead_status'] ?? 'all';
    $export_lead_sub_status = $_POST['export_lead_sub_status'] ?? 'all';
    
    // Build export query - GET ALL LEADS FIRST, THEN FILTER COMMENTS BY DATE
    $export_query = "SELECT l.*, u.name as assigned_user_name 
                    FROM leads l 
                    LEFT JOIN users u ON l.assigned_to = u.id 
                    WHERE l.is_deleted = 0";

    // Add filters to export query (for leads)
    if (!empty($export_lead_status) && $export_lead_status != 'all') {
        $export_query .= " AND l.status = '" . mysqli_real_escape_string($conn, $export_lead_status) . "'";
    }
    if (!empty($export_lead_sub_status) && $export_lead_sub_status != 'all') {
        $export_query .= " AND l.sub_status = '" . mysqli_real_escape_string($conn, $export_lead_sub_status) . "'";
    }
    
    if (!empty($export_assigned_user) && $export_assigned_user != 'all') {
        if ($export_assigned_user == 'unassigned') {
            $export_query .= " AND l.assigned_to IS NULL";
        } else {
            $export_query .= " AND l.assigned_to = '" . mysqli_real_escape_string($conn, $export_assigned_user) . "'";
        }
    }

    if (!empty($export_start_date)) {
        $export_query .= " AND DATE(l.created_at) >= '" . mysqli_real_escape_string($conn, $export_start_date) . "'";
    }
    if (!empty($export_end_date)) {
        $export_query .= " AND DATE(l.created_at) <= '" . mysqli_real_escape_string($conn, $export_end_date) . "'";
    }
    
    
    $export_query .= " ORDER BY l.created_at DESC";
    // echo $export_query; exit;
    $export_result = mysqli_query($conn, $export_query);
    
    // Check if we have data
    $lead_count = mysqli_num_rows($export_result);
    
    if ($lead_count > 0) {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads_export_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
        
        // CSV headers
        $headers = array(
            'S.No',
            'Lead ID', 
            'Name',
            'Email',
            'Phone',
            'Status',
            'Sub Status',
            'Type',
            'Assigned To',
            'Address',
            'City',
            'State',
            'Category',
            'Follow-up Date',
            'Created Date',
            'Updated Date',
            'Comments History'
        );
        
        fputcsv($output, $headers);
        
        // Export data
        $sno = 1;
        while ($row = mysqli_fetch_assoc($export_result)) {
            // Fetch comments for this lead - FILTER BY COMMENT DATE FOR EXPORT
            $lead_id = $row['id'];
            
            // Check if comments table has is_deleted column
            $check_columns_query = "SHOW COLUMNS FROM comments LIKE 'is_deleted'";
            $check_columns_result = mysqli_query($conn, $check_columns_query);
            $has_soft_delete = (mysqli_num_rows($check_columns_result) > 0);
            
            $comments_query = "SELECT c.comments, c.datetime, u.name as user_name 
                              FROM comments c 
                              LEFT JOIN users u ON c.created_by = u.id 
                              WHERE c.leadid = '$lead_id'";
            
            if ($has_soft_delete) {
                $comments_query .= " AND c.is_deleted = 0";
            }
            
            // Apply COMMENT date filters for export
            if (!empty($export_start_date)) {
                $comments_query .= " AND DATE(c.datetime) >= '" . mysqli_real_escape_string($conn, $export_start_date) . "'";
            }
            if (!empty($export_end_date)) {
                $comments_query .= " AND DATE(c.datetime) <= '" . mysqli_real_escape_string($conn, $export_end_date) . "'";
            }
            
            $comments_query .= " ORDER BY c.datetime DESC";
            
            $comments_result = mysqli_query($conn, $comments_query);
            
            $comments_text = "";
            if ($comments_result && mysqli_num_rows($comments_result) > 0) {
                $comment_count = 0;
                while ($comment_row = mysqli_fetch_assoc($comments_result)) {
                    $comment_count++;
                    $comment_date = date('M j, Y g:i A', strtotime($comment_row['datetime']));
                    $comment_text = $comment_row['comments'];
                    $user_name = $comment_row['user_name'] ?: 'System';
                    
                    $comments_text .= "Comment $comment_count: [$comment_date] $user_name - $comment_text | ";
                }
                $comments_text = rtrim($comments_text, " | ");
            } else {
                $comments_text = "No comments";
            }
            
            $data = array(
                $sno++,
                $row['id'],
                $row['name'],
                $row['email'],
                $row['phoneno'],
                ucfirst(str_replace('_', ' ', $row['status'])),
                $row['sub_status'] ?: 'Not Set',
                ucfirst($row['type']),
                $row['assigned_user_name'] ?: 'Unassigned',
                $row['address'],
                $row['city'],
                $row['state'],
                $row['category'],
                $row['followup_date'] ? date('M j, Y g:i A', strtotime($row['followup_date'])) : 'Not Set',
                date('M j, Y g:i A', strtotime($row['created_at'])),
                date('M j, Y g:i A', strtotime($row['updated_at'])),
                $comments_text
            );
            fputcsv($output, $data);
        }
        
        fclose($output);
        exit;
        
    } else {
        $_SESSION['error'] = "No leads found to export with the selected filters.";
        header("Location: leads_management.php");
        exit;
    }
}

// Handle Comment and Status Submission - UPDATED VERSION
if (isset($_POST['add_comment'])) {
    $lead_id = mysqli_real_escape_string($conn, $_POST['lead_id']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $sub_status = isset($_POST['sub_status']) ? mysqli_real_escape_string($conn, $_POST['sub_status']) : null;
    
    $followup_date = !empty($_POST['followup_date']) ? mysqli_real_escape_string($conn, $_POST['followup_date']) : null;
    
    // Build update query
    $update_status = "UPDATE leads SET status = '$status', updated_at = NOW()";
    if ($sub_status) {
        $update_status .= ", sub_status = '$sub_status'";
    } else {
        $update_status .= ", sub_status = NULL"; // Clear sub_status if not provided
    }
    $update_status .= " WHERE id = '$lead_id'";
    
    $insert_comment = "INSERT INTO comments (leadid, comments, datetime, created_by) 
                      VALUES ('$lead_id', '$comment', NOW(), '{$_SESSION['super_admin_user_id']}')";
    
    mysqli_begin_transaction($conn);
    
    try {
        if (!mysqli_query($conn, $update_status)) {
            throw new Exception("Error updating status: " . mysqli_error($conn));
        }
        
        if (!mysqli_query($conn, $insert_comment)) {
            throw new Exception("Error adding comment: " . mysqli_error($conn));
        }
        
        if ($followup_date) {
            $update_followup = "UPDATE leads SET followup_date = '$followup_date' WHERE id = '$lead_id'";
            if (!mysqli_query($conn, $update_followup)) {
                throw new Exception("Error updating follow-up date: " . mysqli_error($conn));
            }
        }
        
        mysqli_commit($conn);
        $_SESSION['message'] = "Status updated and comment added successfully!";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: leads_management.php");
    exit;
}

// Handle Assign Leads to User (Checkbox based)
if (isset($_POST['assign_to_user'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['assign_user_id']);
    $lead_ids = $_POST['lead_ids'] ?? [];
    
    if (empty($user_id)) {
        $_SESSION['error'] = "Please select a user to assign leads.";
    } elseif (empty($lead_ids)) {
        $_SESSION['error'] = "Please select at least one lead to assign.";
    } else {
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($lead_ids as $lead_id) {
            $lead_id = mysqli_real_escape_string($conn, $lead_id);
            
            $current_assigned_query = "SELECT assigned_to FROM leads WHERE id = '$lead_id'";
            $current_assigned_result = mysqli_query($conn, $current_assigned_query);
            $current_assigned = mysqli_fetch_assoc($current_assigned_result);
            $previous_user_id = $current_assigned['assigned_to'];
            
            $update_query = "UPDATE leads SET assigned_to = '$user_id', updated_at = NOW() WHERE id = '$lead_id'";
            
            if (mysqli_query($conn, $update_query)) {
                $successCount++;
                
                $history_query = "INSERT INTO lead_assignment_history (lead_id, previous_user_id, new_user_id, assigned_by, assigned_at) 
                                 VALUES ('$lead_id', " . ($previous_user_id ? "'$previous_user_id'" : "NULL") . ", '$user_id', '{$_SESSION['super_admin_user_id']}', NOW())";
                mysqli_query($conn, $history_query);
                
            } else {
                $errorCount++;
            }
        }
        
        if ($errorCount == 0) {
            $_SESSION['message'] = "Successfully assigned $successCount leads to user.";
        } else {
            $_SESSION['message'] = "Assigned $successCount leads, $errorCount failed.";
        }
    }
    
    header("Location: leads_management.php");
    exit;
}

// Get filter values FOR DISPLAY (LEAD CREATED DATE BASED)
// Set default to last 7 days
$default_start_date = date('Y-m-d', strtotime('-7 days'));
$default_end_date = date('Y-m-d');

$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date = $_GET['end_date'] ?? $default_end_date;
$assigned_user = $_GET['assigned_user'] ?? '';
$lead_status = $_GET['lead_status'] ?? 'all';
$sub_status_filter = $_GET['sub_status'] ?? 'all';

// NEW: Comment Date Filters
$comment_start_date = $_GET['comment_start_date'] ?? '';
$comment_end_date = $_GET['comment_end_date'] ?? '';

// Build query for ALL leads - DISPLAY FILTERS (CREATED DATE BASED)
$leads_query = "SELECT l.*, u.name as assigned_user_name 
                FROM leads l 
                LEFT JOIN users u ON l.assigned_to = u.id 
                WHERE l.is_deleted = 0";

// Add display filters - LEAD CREATED DATE BASED
if (!empty($lead_status) && $lead_status != 'all') {
    $leads_query .= " AND l.status = '" . mysqli_real_escape_string($conn, $lead_status) . "'";
}

// Add sub status filter
if (!empty($sub_status_filter) && $sub_status_filter != 'all') {
    $leads_query .= " AND l.sub_status = '" . mysqli_real_escape_string($conn, $sub_status_filter) . "'";
}

if (!empty($start_date)) {
    $leads_query .= " AND DATE(l.created_at) >= '" . mysqli_real_escape_string($conn, $start_date) . "'";
}
if (!empty($end_date)) {
    $leads_query .= " AND DATE(l.created_at) <= '" . mysqli_real_escape_string($conn, $end_date) . "'";
}

if (!empty($assigned_user) && $assigned_user != 'all') {
    if ($assigned_user == 'unassigned') {
        $leads_query .= " AND l.assigned_to IS NULL";
    } else {
        $leads_query .= " AND l.assigned_to = '" . mysqli_real_escape_string($conn, $assigned_user) . "'";
    }
}

// NEW: COMMENT DATE FILTER - YEH IMPORTANT HAI
if (!empty($comment_start_date) || !empty($comment_end_date)) {
    $leads_query .= " AND l.id IN (
        SELECT DISTINCT c.leadid 
        FROM comments c 
        WHERE c.is_deleted = 0";
    
    if (!empty($comment_start_date)) {
        $leads_query .= " AND DATE(c.datetime) >= '" . mysqli_real_escape_string($conn, $comment_start_date) . "'";
    }
    if (!empty($comment_end_date)) {
        $leads_query .= " AND DATE(c.datetime) <= '" . mysqli_real_escape_string($conn, $comment_end_date) . "'";
    }
    
    $leads_query .= ")";
}

$leads_query .= " ORDER BY l.created_at DESC";
$leads_result = mysqli_query($conn, $leads_query);

// Check for query errors
if (!$leads_result) {
    $_SESSION['error'] = "Database error: " . mysqli_error($conn);
}

// Fetch all users with role = 1 (telecallers)
$users_query = "SELECT id, name FROM users WHERE role = 1 AND status = 1 AND is_deleted = 0 ORDER BY name";
$users_result = mysqli_query($conn, $users_query);

$users = [];
if ($users_result && mysqli_num_rows($users_result) > 0) {
    $users = mysqli_fetch_all($users_result, MYSQLI_ASSOC);
}

// Fetch assigned users for filter
$assigned_users_query = "SELECT DISTINCT u.id, u.name 
                        FROM users u 
                        INNER JOIN leads l ON u.id = l.assigned_to 
                        WHERE u.role = 1 AND u.status = 1 AND u.is_deleted = 0 
                        ORDER BY u.name";
$assigned_users_result = mysqli_query($conn, $assigned_users_query);
$assigned_users = [];
if ($assigned_users_result && mysqli_num_rows($assigned_users_result) > 0) {
    $assigned_users = mysqli_fetch_all($assigned_users_result, MYSQLI_ASSOC);
}

// Get ALL duplicate phone numbers
$duplicate_phones_query = "
    SELECT phoneno, COUNT(*) as count 
    FROM leads 
    WHERE is_deleted = 0  
    GROUP BY phoneno 
    HAVING COUNT(*) > 1
";
$duplicate_phones_result = mysqli_query($conn, $duplicate_phones_query);
$duplicate_phones = [];
if ($duplicate_phones_result && mysqli_num_rows($duplicate_phones_result) > 0) {
    while ($row = mysqli_fetch_assoc($duplicate_phones_result)) {
        $duplicate_phones[$row['phoneno']] = $row['count'];
    }
}
// Store all leads data for duplicate checking
$all_leads_data = [];
if ($leads_result && mysqli_num_rows($leads_result) > 0) {
    mysqli_data_seek($leads_result, 0);
    while ($lead = mysqli_fetch_assoc($leads_result)) {
        // Get last comment time for each lead
        $lead_id = $lead['id'];
        $last_comment_query = "SELECT datetime FROM comments 
                              WHERE leadid = '$lead_id' 
                              ORDER BY datetime DESC LIMIT 1";
        $last_comment_result = mysqli_query($conn, $last_comment_query);
        $last_comment_time = null;
        
        if ($last_comment_result && mysqli_num_rows($last_comment_result) > 0) {
            $last_comment = mysqli_fetch_assoc($last_comment_result);
            $last_comment_time = $last_comment['datetime'];
        }
        
        $lead['last_comment_time'] = $last_comment_time;
        $all_leads_data[] = $lead;
    }
}

// Store all leads data for duplicate checking
$all_leads_data = [];
if ($leads_result && mysqli_num_rows($leads_result) > 0) {
    mysqli_data_seek($leads_result, 0);
    while ($lead = mysqli_fetch_assoc($leads_result)) {
        // Get last comment time for each lead (WITH COMMENT DATE FILTERS)
        $lead_id = $lead['id'];
        
        // Check if comments table has is_deleted column
        $check_columns_query = "SHOW COLUMNS FROM comments LIKE 'is_deleted'";
        $check_columns_result = mysqli_query($conn, $check_columns_query);
        $has_soft_delete = (mysqli_num_rows($check_columns_result) > 0);
        
        $last_comment_query = "SELECT datetime FROM comments 
                              WHERE leadid = '$lead_id'";
        
        if ($has_soft_delete) {
            $last_comment_query .= " AND is_deleted = 0";
        }
        
        // Apply comment date filters if set
        if (!empty($comment_start_date)) {
            $last_comment_query .= " AND DATE(datetime) >= '" . mysqli_real_escape_string($conn, $comment_start_date) . "'";
        }
        if (!empty($comment_end_date)) {
            $last_comment_query .= " AND DATE(datetime) <= '" . mysqli_real_escape_string($conn, $comment_end_date) . "'";
        }
        
        $last_comment_query .= " ORDER BY datetime DESC LIMIT 1";
        
        $last_comment_result = mysqli_query($conn, $last_comment_query);
        $last_comment_time = null;
        
        if ($last_comment_result && mysqli_num_rows($last_comment_result) > 0) {
            $last_comment = mysqli_fetch_assoc($last_comment_result);
            $last_comment_time = $last_comment['datetime'];
        }
        
        $lead['last_comment_time'] = $last_comment_time;
        $all_leads_data[] = $lead;
    }
}

// Fetch assignment history for all leads
$assignment_history = [];
foreach ($all_leads_data as $lead) {
    $lead_id = $lead['id'];
    $history_query = "SELECT lah.*, 
                             prev_u.name as previous_user_name,
                             new_u.name as new_user_name,
                             assigned_u.name as assigned_by_name
                      FROM lead_assignment_history lah
                      LEFT JOIN users prev_u ON lah.previous_user_id = prev_u.id
                      LEFT JOIN users new_u ON lah.new_user_id = new_u.id  
                      LEFT JOIN users assigned_u ON lah.assigned_by = assigned_u.id
                      WHERE lah.lead_id = '$lead_id'
                      ORDER BY lah.assigned_at DESC";
    $history_result = mysqli_query($conn, $history_query);
    
    if ($history_result && mysqli_num_rows($history_result) > 0) {
        $assignment_history[$lead_id] = mysqli_fetch_all($history_result, MYSQLI_ASSOC);
    }
}

// Fetch all statuses from status table
$status_query = "SELECT * FROM status WHERE is_deleted = 0 AND status = 1 ORDER BY name";
$status_result = mysqli_query($conn, $status_query);
$all_statuses = [];
if ($status_result && mysqli_num_rows($status_result) > 0) {
    $all_statuses = mysqli_fetch_all($status_result, MYSQLI_ASSOC);
}

// Fetch all sub statuses from sub_status table
$sub_status_query = "SELECT * FROM sub_status WHERE is_deleted = 0 AND status = 1 ORDER BY name";
$sub_status_result = mysqli_query($conn, $sub_status_query);
$all_sub_statuses = [];
if ($sub_status_result && mysqli_num_rows($sub_status_result) > 0) {
    $all_sub_statuses = mysqli_fetch_all($sub_status_result, MYSQLI_ASSOC);
}

// Create status to sub status mapping
$status_substatus_map = [];
foreach ($all_statuses as $status) {
    $status_name = $status['name'];
    $status_id = $status['id'];
    
    // Get sub statuses for this status
    $sub_status_query = "SELECT ss.* FROM sub_status ss 
                        WHERE ss.status_id = '$status_id' 
                        AND ss.is_deleted = 0 AND ss.status = 1 
                        ORDER BY ss.name";
    $sub_status_result = mysqli_query($conn, $sub_status_query);
    
    $status_substatus_map[$status_name] = [
        'has_sub_status' => false,
        'sub_statuses' => []
    ];
    
    if ($sub_status_result && mysqli_num_rows($sub_status_result) > 0) {
        $status_substatus_map[$status_name]['has_sub_status'] = true;
        $status_substatus_map[$status_name]['sub_statuses'] = mysqli_fetch_all($sub_status_result, MYSQLI_ASSOC);
    }
}

// Get status counts for statistics - SUPER ADMIN VERSION WITH FILTERS
$status_counts_query = "
    SELECT status, COUNT(*) as count 
    FROM leads 
    WHERE is_deleted = 0 
    GROUP BY status
";
$status_counts_result = mysqli_query($conn, $status_counts_query);
$status_counts = [];
$total_leads = 0;
if ($status_counts_result) {
    while ($row = mysqli_fetch_assoc($status_counts_result)) {
        $status_counts[$row['status']] = $row['count'];
        $total_leads += $row['count'];
    }
}

// Get FILTERED status counts for leads overview
$filtered_status_counts = [];
if (!empty($all_leads_data)) {
    foreach ($all_leads_data as $lead) {
        $status = $lead['status'];
        if ($status) {
            $filtered_status_counts[$status] = ($filtered_status_counts[$status] ?? 0) + 1;
        }
    }
}

// Get assigned user counts
$user_counts_query = "
    SELECT 
        u.name as user_name,
        u.id as user_id,
        COUNT(l.id) as lead_count
    FROM leads l 
    LEFT JOIN users u ON l.assigned_to = u.id 
    WHERE l.is_deleted = 0 
    GROUP BY l.assigned_to, u.name, u.id
    ORDER BY lead_count DESC
    LIMIT 10
";
$user_counts_result = mysqli_query($conn, $user_counts_query);
$user_counts = [];
if ($user_counts_result) {
    $user_counts = mysqli_fetch_all($user_counts_result, MYSQLI_ASSOC);
}

// Get unassigned leads count
$unassigned_count_query = "SELECT COUNT(*) as count FROM leads WHERE is_deleted = 0 AND assigned_to IS NULL";
$unassigned_count_result = mysqli_query($conn, $unassigned_count_query);
$unassigned_count = 0;
if ($unassigned_count_result) {
    $unassigned_data = mysqli_fetch_assoc($unassigned_count_result);
    $unassigned_count = $unassigned_data['count'];
}

// Get FILTERED unassigned count
$filtered_unassigned_count = count(array_filter($all_leads_data, function($lead) { 
    return empty($lead['assigned_to']); 
}));

// Get FILTERED user counts for top users
$filtered_user_counts = [];
if (!empty($all_leads_data)) {
    foreach ($all_leads_data as $lead) {
        $user_name = $lead['assigned_user_name'] ?: 'Unassigned';
        if (!isset($filtered_user_counts[$user_name])) {
            $filtered_user_counts[$user_name] = 0;
        }
        $filtered_user_counts[$user_name]++;
    }
}
arsort($filtered_user_counts);
$top_filtered_users = array_slice($filtered_user_counts, 0, 5, true);

// Get assigned user counts
$user_counts_query = "
    SELECT 
        u.name as user_name,
        u.id as user_id,
        COUNT(l.id) as lead_count
    FROM leads l 
    LEFT JOIN users u ON l.assigned_to = u.id 
    WHERE l.is_deleted = 0 
    GROUP BY l.assigned_to, u.name, u.id
    ORDER BY lead_count DESC
    LIMIT 10
";
$user_counts_result = mysqli_query($conn, $user_counts_query);
$user_counts = [];
if ($user_counts_result) {
    $user_counts = mysqli_fetch_all($user_counts_result, MYSQLI_ASSOC);
}

// Get unassigned leads count
$unassigned_count_query = "SELECT COUNT(*) as count FROM leads WHERE is_deleted = 0 AND assigned_to IS NULL";
$unassigned_count_result = mysqli_query($conn, $unassigned_count_query);
$unassigned_count = 0;
if ($unassigned_count_result) {
    $unassigned_data = mysqli_fetch_assoc($unassigned_count_result);
    $unassigned_count = $unassigned_data['count'];
}

// Get FILTERED unassigned count
$filtered_unassigned_count = count(array_filter($all_leads_data, function($lead) { 
    return empty($lead['assigned_to']); 
}));

// Get FILTERED user counts for top users
$filtered_user_counts = [];
if (!empty($all_leads_data)) {
    foreach ($all_leads_data as $lead) {
        $user_name = $lead['assigned_user_name'] ?: 'Unassigned';
        if (!isset($filtered_user_counts[$user_name])) {
            $filtered_user_counts[$user_name] = 0;
        }
        $filtered_user_counts[$user_name]++;
    }
}
arsort($filtered_user_counts);
$top_filtered_users = array_slice($filtered_user_counts, 0, 5, true);

// Show filter summary
$filter_summary = "";
if (!empty($start_date) || !empty($end_date) || !empty($lead_status) || !empty($assigned_user) || !empty($sub_status_filter) || !empty($comment_start_date) || !empty($comment_end_date)) {
    $filter_summary = "Applied Filters: ";
    $filters = [];
    
    if (!empty($start_date)) $filters[] = "Lead Created From: " . date('d-m-Y', strtotime($start_date));
    if (!empty($end_date)) $filters[] = "Lead Created To: " . date('d-m-Y', strtotime($end_date));
    if (!empty($lead_status) && $lead_status != 'all') $filters[] = "Status: " . ucfirst($lead_status);
    if (!empty($sub_status_filter) && $sub_status_filter != 'all') $filters[] = "Sub Status: " . ucfirst($sub_status_filter);
    if (!empty($assigned_user) && $assigned_user != 'all') {
        if ($assigned_user == 'unassigned') {
            $filters[] = "Assigned: Unassigned";
        } else {
            $user_name = "Unknown";
            foreach ($assigned_users as $user) {
                if ($user['id'] == $assigned_user) {
                    $user_name = $user['name'];
                    break;
                }
            }
            $filters[] = "Assigned: " . $user_name;
        }
    }
    
    // NEW: Add comment date filters to summary
    if (!empty($comment_start_date)) $filters[] = "Comment From: " . date('d-m-Y', strtotime($comment_start_date));
    if (!empty($comment_end_date)) $filters[] = "Comment To: " . date('d-m-Y', strtotime($comment_end_date));
    
    $filter_summary .= implode(", ", $filters);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads Management - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/searchpanes/2.1.2/css/searchPanes.dataTables.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/select/1.7.0/css/select.dataTables.min.css" rel="stylesheet">
    <style>
        /* Time Display Styles */
        .time-since-comment {
            font-size: 10px;
            color: #666;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            margin-top: 3px;
            display: inline-block;
            border: 1px solid #dee2e6;
        }

        .time-urgent {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
            font-weight: bold;
        }

        .time-critical {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
            font-weight: bold;
        }

        .phone-with-time {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .comment-popup, .delete-popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
            z-index: 1000;
            width: 500px;
            max-width: 90%;
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        .comments-history {
            max-height: 200px;
            overflow-y: auto;
            margin-top: 15px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .comment-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            position: relative;
        }
        .comment-date {
            color: #666;
            font-size: 12px;
        }
        .comments-history-container {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 8px;
            background: #f8f9fa;
            font-size: 12px;
        }
        .comment-history-item {
            padding: 6px;
            border-bottom: 1px solid #eee;
            margin-bottom: 5px;
            border-radius: 4px;
            background: white;
            position: relative;
        }
        .comment-history-user {
            font-weight: bold;
            color: #0d6efd;
            font-size: 11px;
        }
        .comment-history-text {
            font-size: 11px;
            margin: 2px 0;
            color: #333;
        }
        .comment-history-time {
            font-size: 10px;
            color: #666;
        }
        .badge-hot { background-color: #dc3545; }
        .badge-warm { background-color: #fd7e14; }
        .badge-cold { background-color: #0dcaf0; }
        .badge-new { background-color: #0d6efd; }
        .badge-processing { background-color: #6f42c1; }
        .badge-follow_up { background-color: #ffc107; color: #000; }
        .badge-confirmed { background-color: #198754; }
        .badge-converted { background-color: #20c997; }
        .badge-rejected { background-color: #dc3545; }
        .badge-duplicate { background-color: #fd7e14; }
        .badge-trash { background-color: #6c757d; }
        .badge-assigned { background-color: #6f42c1; }
        .status-badge {
            font-size: 0.8em;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .assign-section {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #b3d9ff;
        }
        .delete-section {
            background: #ffe6e6;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ffb3b3;
        }
        .export-section {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%);
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .export-section .card {
            border: 2px solid #198754;
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.15);
        }
        .export-section .btn-success {
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
            border: none;
            font-weight: 600;
            padding: 10px 15px;
        }
        .export-section .btn-success:hover {
            background: linear-gradient(135deg, #157347 0%, #146c43 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(25, 135, 84, 0.3);
        }
        .select-all-container {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .lead-checkbox {
            transform: scale(1.2);
        }
        .duplicate-phone {
            background-color: #fff3cd !important;
            border: 2px solid #ffc107 !important;
            font-weight: bold;
            position: relative;
        }
        .duplicate-phone::after {
            content: "DUPLICATE";
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: bold;
        }
        .phone-link {
            color: #0d6efd;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        .phone-link:hover {
            background-color: #0d6efd;
            color: white;
            text-decoration: none;
        }
        .duplicate-count {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
            margin-left: 5px;
        }
        .duplicate-alert {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
        }
        .lead-status {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
        }
        .download-status {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            min-width: 300px;
        }
        .export-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
        }
        .assignment-history-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .history-table {
            font-size: 0.85em;
            margin-bottom: 0;
        }
        .history-badge {
            font-size: 0.75em;
            padding: 3px 6px;
        }
        .toggle-history {
            cursor: pointer;
            color: #0d6efd;
            font-weight: bold;
        }
        .toggle-history:hover {
            text-decoration: underline;
        }
        .history-row {
            background-color: #f8f9fa !important;
        }
        .history-row td {
            border-top: none !important;
            padding: 0 !important;
        }
        .history-content {
            padding: 15px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            margin: 5px 0;
        }
        .table-responsive {
            overflow-x: auto;
        }
        #leadsTable {
            border-collapse: separate;
            border-spacing: 0;
        }
        #leadsTable tbody tr {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        #leadsTable tbody tr:hover {
            background-color: #f8f9fa;
        }
        .no-comments {
            color: #6c757d;
            font-style: italic;
            text-align: center;
            padding: 10px;
        }
        .filter-summary {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .updated-at-info {
            font-size: 11px;
            color: #666;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            border-left: 3px solid #0d6efd;
        }
        .comment-text-only {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 8px;
            margin: 2px 0;
            font-size: 12px;
            position: relative;
        }
        .btn-delete {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            color: white;
        }
        .btn-delete:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            color: white;
        }
        .delete-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
        }
        .comment-delete-btn {
            position: absolute;
            top: 2px;
            right: 2px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 3px;
            width: 20px;
            height: 20px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0.7;
        }
        .comment-delete-btn:hover {
            opacity: 1;
        }
        .comment-checkbox {
            position: absolute;
            top: 2px;
            left: 2px;
            transform: scale(0.8);
        }
        .delete-history-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .delete-history-section table {
            font-size: 0.8em;
        }
        .delete-history-section th {
            background: #dc3545;
            color: white;
            font-weight: 600;
        }
        .lead-id-with-action {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .lead-id-text {
            font-weight: bold;
            color: #0d6efd;
        }
        .btn-add-comment-small {
            padding: 2px 6px;
            font-size: 10px;
            border-radius: 3px;
        }
        .sub-status-container {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        .sub-status-required {
            color: #dc3545;
            font-weight: bold;
        }
        /* New Styles for Leads Overview */
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            height: 100%;
        }
        .stats-card-secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            height: 100%;
        }
        .status-filter-badge {
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85em;
            padding: 6px 10px;
        }
        .status-filter-badge:hover {
            transform: scale(1.05);
        }
        .filter-badge-active {
            border: 2px solid #fff;
            box-shadow: 0 0 10px rgba(255,255,255,0.5);
        }
        .user-stats {
            max-height: 120px;
            overflow-y: auto;
        }
        .user-stats-item {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 8px;
            padding: 5px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
        }
        /* WhatsApp Icon Styles */
        .whatsapp-icon {
            color: #25D366;
            font-size: 16px;
            margin-left: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .whatsapp-icon:hover {
            transform: scale(1.2);
        }
        .phone-with-whatsapp {
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }
        /* Search Box Styles */
        .dataTables_filter {
            margin-bottom: 15px;
        }
        .dataTables_filter label {
            font-weight: bold;
        }
        .dataTables_filter input {
            margin-left: 10px;
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        /* Lead IDs Input Styles */
        .lead-ids-input-container {
            position: relative;
        }
        .input-actions {
            position: absolute;
            top: 35px;
            right: 10px;
        }
        .input-actions .btn {
            padding: 2px 6px;
            font-size: 12px;
        }
        /* Enhanced Search Styles */
        .search-panel {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .search-panel h6 {
            color: #495057;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .column-search-item {
            margin-bottom: 10px;
        }
        .column-search-item label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 5px;
        }
        .quick-search-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .quick-search-btn {
            font-size: 0.8em;
            padding: 4px 8px;
        }
        .dt-search-clear {
            margin-left: 5px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 2px 6px;
            font-size: 0.8em;
            cursor: pointer;
        }
        .dt-search-clear:hover {
            background: #5a6268;
        }
        /* DataTables Custom Styles */
        .dataTables_wrapper .dataTables_filter {
            float: none;
            text-align: left;
            margin-bottom: 15px;
        }
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 6px 12px;
            width: 300px;
        }
        .dataTables_length {
            margin-bottom: 15px;
        }
        .dt-buttons {
            margin-bottom: 15px;
        }
        /* Column Specific Search Styles */
        .column-search-container {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .search-header {
            background: #e9ecef;
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-weight: 600;
            color: #495057;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include('common/header.php'); ?>
    
    <!-- Download Status Indicator -->
    <div id="downloadStatus" class="download-status alert alert-info alert-dismissible fade show">
        <i class="fas fa-spinner fa-spin me-2"></i>
        <span id="statusMessage">Preparing your download...</span>
        <button type="button" class="btn-close" onclick="hideDownloadStatus()"></button>
    </div>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include('common/sidebar.php'); ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 mt-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h2>Leads Management - All Leads (Super Admin)</h2>
                    <?php if (!empty($duplicate_phones)): ?>
                        <div class="alert alert-warning d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <span><?php echo count($duplicate_phones); ?> duplicate phone numbers found in database</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <!-- Filter Summary -->
                <?php if (!empty($filter_summary)): ?>
                <div class="filter-summary">
                    <i class="fas fa-filter me-2"></i><?php echo $filter_summary; ?>
                    <span class="badge bg-primary ms-2"><?php echo count($all_leads_data); ?> leads found</span>
                </div>
                <?php endif; ?>

                <!-- Status Statistics - SUPER ADMIN VERSION WITH FILTERED COUNTS -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="stats-card">
                            <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Leads Overview - All Users <small class="text-light">(Filtered Results)</small></h5>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="status-filter-badge badge bg-primary <?php echo $lead_status == 'all' ? 'filter-badge-active' : ''; ?>" 
                                      onclick="filterByStatus('all')">
                                    All Leads: <?php echo count($all_leads_data); ?>
                                </span>
                                <?php 
                                // Show counts for filtered statuses
                                foreach ($filtered_status_counts as $status => $count): 
                                    $is_active = ($lead_status == $status);
                                ?>
                                    <span class="status-filter-badge badge badge-<?php echo strtolower(str_replace(' ', '_', $status)); ?> <?php echo $is_active ? 'filter-badge-active' : ''; ?>" 
                                          onclick="filterByStatus('<?php echo $status; ?>')">
                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>: <?php echo $count; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="status-filter-badge badge bg-warning text-dark <?php echo $assigned_user == 'unassigned' ? 'filter-badge-active' : ''; ?>" 
                                      onclick="filterByUser('unassigned')">
                                    Unassigned Leads: <?php echo $filtered_unassigned_count; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card-secondary">
                            <h5 class="mb-3"><i class="fas fa-users me-2"></i>Top Users by Leads <small class="text-light">(Filtered)</small></h5>
                            <div class="user-stats">
                                <?php if (!empty($top_filtered_users)): ?>
                                    <?php foreach ($top_filtered_users as $user_name => $count): ?>
                                        <div class="user-stats-item">
                                            <small class="text-white flex-grow-1">
                                                <?php echo htmlspecialchars($user_name); ?>
                                            </small>
                                            <span class="badge bg-dark"><?php echo $count; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <small class="text-white-50">No user data available</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bulk Assign by Lead IDs Section -->
                <div class="assign-section">
                    <h5><i class="fas fa-users me-2"></i>Bulk Assign Leads by IDs</h5>
                    <form method="POST" id="bulkAssignForm">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Select User <span class="text-danger">*</span></label>
                                <select class="form-select" name="assign_user_id" id="bulkAssignUserId" required>
                                    <option value="">Select User</option>
                                    <?php if (count($users) > 0): ?>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>">
                                                <?php echo htmlspecialchars($user['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">No users found</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="lead-ids-input-container">
                                    <label class="form-label">Enter Lead IDs <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="lead_ids_input" id="leadIdsInput" rows="3" 
                                              placeholder="Enter lead IDs separated by commas, spaces, or new lines&#10;Example: 123, 456, 789&#10;Or: 123 456 789&#10;Or: 123&#10;456&#10;789" 
                                              required oninput="updateLeadIdsCounter()"></textarea>
                                    <div class="input-actions">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearLeadIds()" title="Clear">
                                            <i class="fas fa-eraser"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="form-text">
                                    Separate multiple lead IDs with commas, spaces, or new lines
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" name="bulk_assign_by_ids" class="btn btn-success w-100">
                                    <i class="fas fa-user-check me-1"></i>Assign Leads
                                </button>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        You can paste multiple lead IDs from Excel or any other source
                                    </small>
                                    <div id="leadIdsCounterContainer"></div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Export Button Section -->
                <div class="export-section">
                    <div class="card">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-download me-2"></i>Export Leads to CSV/Excel</h5>
                            <button type="button" class="btn btn-light btn-sm" onclick="exportFilteredData()">
                                <i class="fas fa-file-export me-1"></i>Export Current Data
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="export-info">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <strong>Export Note:</strong> This will export all data currently visible in the table below with applied filters including comment date filters.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters Section - DISPLAY FILTERS (LEAD CREATED DATE BASED) -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Start Date (Lead Created)</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">End Date (Lead Created)</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        
                        <!-- NEW: Comment Date Filters -->
                        <div class="col-md-2">
                            <label class="form-label">Comment Start Date</label>
                            <input type="date" class="form-control" name="comment_start_date" value="<?php echo htmlspecialchars($comment_start_date); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Comment End Date</label>
                            <input type="date" class="form-control" name="comment_end_date" value="<?php echo htmlspecialchars($comment_end_date); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Lead Status</label>
                            <select class="form-select" name="lead_status" id="filterLeadStatus" onchange="updateFilterSubStatus()">
                                <option value="all">All Status</option>
                                <?php if (count($all_statuses) > 0): ?>
                                    <?php foreach ($all_statuses as $status): ?>
                                        <option value="<?php echo htmlspecialchars($status['name']); ?>" <?php echo ($lead_status == $status['name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($status['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sub Status</label>
                            <select class="form-select" name="sub_status" id="filterSubStatus">
                                <option value="all">All Sub Status</option>
                                <!-- Sub status options will be dynamically populated -->
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Assigned To</label>
                            <select class="form-select" name="assigned_user">
                                <option value="all">All Users</option>
                                <option value="unassigned" <?php echo ($assigned_user == 'unassigned') ? 'selected' : ''; ?>>Unassigned</option>
                                <?php if (count($assigned_users) > 0): ?>
                                    <?php foreach ($assigned_users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo ($assigned_user == $user['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                            <a href="leads_management.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="select-all-container">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                                <label class="form-check-label" for="selectAll">
                                    Select All Leads on This Page
                                </label>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table id="leadsTable" class="table table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="50px">Select</th>
                                        <th>Category</th>
                                        <th>Lead ID</th>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Comments</th>
                                        <th>Status</th>
                                        <th>Assigned To</th>
                                        <th>Created Date</th>
                                        <th>Last Updated</th>
                                        <th>ID_Source</th>
                                        <th>Email</th>
                                        <th>Address</th>
                                        <th>City</th>
                                        <th>State</th>
                                        <th>S.No</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sno = 1;
                                    if (!empty($all_leads_data)):
                                        foreach ($all_leads_data as $lead):
                                            
                                            // Check if comments table has is_deleted column
                                            $check_columns_query = "SHOW COLUMNS FROM comments LIKE 'is_deleted'";
                                            $check_columns_result = mysqli_query($conn, $check_columns_query);
                                            $has_soft_delete = (mysqli_num_rows($check_columns_result) > 0);
                                            
                                            // Fetch comments with user names for this lead
                                            $comments_query = "SELECT c.*, u.name as user_name 
                                                              FROM comments c 
                                                              LEFT JOIN users u ON c.created_by = u.id 
                                                              WHERE c.leadid = '{$lead['id']}'";
                                            
                                            if ($has_soft_delete) {
                                                $comments_query .= " AND c.is_deleted = 0";
                                            }
                                            
                                            $comments_query .= " ORDER BY c.datetime DESC LIMIT 10";
                                            
                                            $comments_result = mysqli_query($conn, $comments_query);
                                            $comments = [];
                                            if ($comments_result) {
                                                $comments = mysqli_fetch_all($comments_result, MYSQLI_ASSOC);
                                            }
                                            
                                            // Check if phone is duplicate
                                            $is_duplicate = isset($duplicate_phones[$lead['phoneno']]);
                                            $duplicate_count = $is_duplicate ? $duplicate_phones[$lead['phoneno']] : 0;
                                            
                                            // Get assignment history
                                            $lead_history = $assignment_history[$lead['id']] ?? [];
                                            
                                            // Get latest comment for updated at info
                                            $latest_comment_query = "SELECT comments, datetime FROM comments 
                                                                   WHERE leadid = '{$lead['id']}'";
                                            if ($has_soft_delete) {
                                                $latest_comment_query .= " AND is_deleted = 0";
                                            }
                                            $latest_comment_query .= " ORDER BY datetime DESC LIMIT 1";
                                            
                                            $latest_comment_result = mysqli_query($conn, $latest_comment_query);
                                            $latest_comment = $latest_comment_result ? mysqli_fetch_assoc($latest_comment_result) : null;
                                        ?>
                                        <!-- Main Lead Row -->
                                        <tr data-lead-id="<?php echo $lead['id']; ?>">
                                            <td>
                                                <input type="checkbox" class="form-check-input lead-checkbox" name="lead_ids[]" value="<?php echo $lead['id']; ?>">
                                            </td>
                                            <td><?php echo htmlspecialchars($lead['category']); ?></td>
                                            <!-- Lead ID Column with Add Comment Button -->
                                            <td>
                                                <div class="lead-id-with-action">
                                                    <span class="lead-id-text"><?php echo htmlspecialchars($lead['id']); ?></span>
                                                    <button class="btn btn-sm btn-outline-primary btn-add-comment-small" 
                                                            onclick="openCommentPopup(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['status']); ?>', '<?php echo htmlspecialchars($lead['sub_status'] ?? ''); ?>')"
                                                            title="Add Comment">
                                                        <i class="fas fa-comment"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td title="<?php echo htmlspecialchars($lead['name']); ?>">
                                                <?php 
                                                $name = $lead['name'];
                                                if (strlen($name) > 32) {
                                                    echo htmlspecialchars(substr($name, 0, 32)) . '...';
                                                } else {
                                                    echo htmlspecialchars($name);
                                                }
                                                ?>
                                            </td>
                                            <td class="<?php echo $is_duplicate ? 'duplicate-phone' : ''; ?>">
                                                <div class="phone-with-whatsapp">
                                                    <a href="phone_leads.php?phone=<?php echo urlencode($lead['phoneno']); ?>" 
                                                       class="phone-link" 
                                                       target="_blank"
                                                       title="Click to view all leads with this phone number (Total: <?php echo $duplicate_count; ?> leads)">
                                                        <?php echo htmlspecialchars($lead['phoneno']); ?>
                                                        <?php if ($is_duplicate): ?>
                                                            <span class="duplicate-count"><?php echo $duplicate_count; ?></span>
                                                        <?php endif; ?>
                                                    </a>
                                                    <i class="fab fa-whatsapp whatsapp-icon" 
                                                       onclick="openWhatsApp('<?php echo htmlspecialchars($lead['phoneno']); ?>')"
                                                       title="Click to open WhatsApp with this number"></i>
                                                </div>

                                                <?php if (!empty($lead['last_comment_time'])): 
                                                    $ist_timezone = new DateTimeZone('Asia/Kolkata');
                                                    $current_time = new DateTime('now', $ist_timezone);
                                                    $currentTime = $current_time->getTimestamp();
                                                    
                                                    $lastCommentTime = strtotime($lead['last_comment_time']);                                                    
                                                    $timeDiff = $currentTime - $lastCommentTime;

                                                    // Convert seconds to readable format
                                                    $timeDisplay = '';
                                                    $timeClass = '';

                                                     if ($timeDiff < 60) {
                                                        $timeDisplay = 'Just now';
                                                    } else if ($timeDiff < 3600) { // Less than 1 hour
                                                        $minutes = floor($timeDiff / 60);
                                                        $timeDisplay = $minutes . ' min ago';
                                                        $timeClass = ($minutes > 30) ? 'time-urgent' : '';
                                                    } elseif ($timeDiff < 86400) { // Less than 1 day
                                                        $hours = floor($timeDiff / 3600);
                                                        $timeDisplay = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
                                                        $timeClass = ($hours > 12) ? 'time-urgent' : '';
                                                    } elseif ($timeDiff < 2592000) { // Less than 30 days
                                                        $days = floor($timeDiff / 86400);
                                                        $timeDisplay = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
                                                        $timeClass = ($days > 7) ? 'time-critical' : '';
                                                    } else { // More than 30 days
                                                        $months = floor($timeDiff / 2592000);
                                                        $timeDisplay = $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
                                                        $timeClass = 'time-critical';
                                                    }
                                                ?>
                                                <span class="time-since-comment <?php echo $timeClass; ?>" 
                                                    title="Last comment: <?php echo date('M j, Y g:i A', $lastCommentTime); ?>">
                                                    ⏱️ <?php echo $timeDisplay; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="time-since-comment" title="No comments yet">
                                                    ⏱️ No comments
                                                </span>
                                            <?php endif; ?>
                                            </td>
                                            <!-- Comments History Column - WITH DELETE BUTTONS -->
                                            <td style="max-width: 300px; min-width: 250px;">
                                                <div class="comments-history-container">
                                                    <?php if (!empty($comments)): ?>
                                                        <?php foreach ($comments as $comment): ?>
                                                            <div class="comment-text-only">
                                                                <input type="checkbox" class="comment-checkbox" name="comment_ids[]" value="<?php echo $comment['id']; ?>" style="display: none;">
                                                                <button class="comment-delete-btn" onclick="openCommentDeletePopup(<?php echo $comment['id']; ?>, '<?php echo htmlspecialchars(addslashes($comment['comments'])); ?>')" title="Delete Comment">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                                <?php echo htmlspecialchars($comment['comments']); ?>
                                                                <div class="comment-history-time">
                                                                    <small>
                                                                        By: <?php echo htmlspecialchars($comment['user_name'] ?: 'System'); ?> | 
                                                                        <?php echo date('M j, g:i A', strtotime($comment['datetime'])); ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div class="no-comments">No comments yet</div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                             <td>
                                                <?php if (!empty($lead['status'])): ?>
                                                    <span class="badge badge-<?php echo strtolower(str_replace(' ', '_', $lead['status'])); ?> status-badge lead-status">
                                                        <?php echo htmlspecialchars(ucfirst($lead['status'])); ?>
                                                    </span>
                                                    <?php if (!empty($lead['sub_status'])): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i><?php echo htmlspecialchars($lead['sub_status']); ?></i>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary status-badge">Not Set</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($lead['assigned_user_name'])): ?>
                                                    <span class="badge badge-assigned status-badge"><?php echo htmlspecialchars($lead['assigned_user_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary status-badge">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($lead['created_at'])); ?></td>                                                                                        
                                            <td>
                                                <?php echo date('M j, Y g:i A', strtotime($lead['updated_at'])); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($lead['source']); ?></td>
                                            <td><?php echo htmlspecialchars($lead['email']); ?></td>
                                            <td><?php echo htmlspecialchars($lead['address']); ?></td>
                                            <td><?php echo htmlspecialchars($lead['city']); ?></td>
                                            <td><?php echo htmlspecialchars($lead['state']); ?></td>
                                            <td><?php echo $sno++; ?></td>
                                            
                                            
                                            <!-- Actions Column - Now only has Delete History -->
                                            <td>
                                                <div class="btn-group-vertical" role="group">
                                                    <button class="btn btn-sm btn-outline-info mb-1" onclick="viewDeleteHistory(<?php echo $lead['id']; ?>)">
                                                        <i class="fas fa-history me-1"></i>Delete History
                                                    </button>
                                                </div>
                                                
                                                <?php if (!empty($lead_history)): ?>
                                                    <div class="mt-1">
                                                        <span class="toggle-history" onclick="toggleHistory(<?php echo $lead['id']; ?>)">
                                                            <i class="fas fa-history me-1"></i>
                                                            Assignment History (<?php echo count($lead_history); ?>)
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        
                                        <!-- Assignment History Row -->
                                        <?php if (!empty($lead_history)): ?>
                                        <tr id="historyRow<?php echo $lead['id']; ?>" class="history-row" style="display: none;">
                                            <td colspan="16" class="p-0">
                                                <div class="history-content">
                                                    <h6 class="mb-3">
                                                        <i class="fas fa-history me-2"></i>
                                                        Assignment History for Lead #<?php echo $lead['id']; ?> - <?php echo htmlspecialchars($lead['name']); ?>
                                                    </h6>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered history-table">
                                                            <thead class="table-warning">
                                                                <tr>
                                                                    <th width="5%">#</th>
                                                                    <th width="25%">From User</th>
                                                                    <th width="25%">To User</th>
                                                                    <th width="20%">Assigned By</th>
                                                                    <th width="25%">Assignment Date</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php 
                                                                $history_sno = 1;
                                                                foreach ($lead_history as $history): 
                                                                ?>
                                                                <tr>
                                                                    <td><?php echo $history_sno++; ?></td>
                                                                    <td>
                                                                        <?php if ($history['previous_user_name']): ?>
                                                                            <span class="badge bg-secondary history-badge"><?php echo htmlspecialchars($history['previous_user_name']); ?></span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-light text-dark history-badge">Unassigned</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <?php if ($history['new_user_name']): ?>
                                                                            <span class="badge bg-success history-badge"><?php echo htmlspecialchars($history['new_user_name']); ?></span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-light text-dark history-badge">Unassigned</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge bg-info history-badge"><?php echo htmlspecialchars($history['assigned_by_name']); ?></span>
                                                                    </td>
                                                                    <td><?php echo date('M j, Y g:i A', strtotime($history['assigned_at'])); ?></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="16" class="text-center">No leads found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Comment Popup -->
    <div class="overlay" id="overlay" onclick="closeAllPopups()"></div>
    <div class="comment-popup" id="commentPopup">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5>Update Status & Add Comment</h5>
            <button type="button" class="btn-close" onclick="closeCommentPopup()"></button>
        </div>
        
        <form method="POST" id="commentForm">
            <input type="hidden" name="lead_id" id="popupLeadId">
            <input type="hidden" name="add_comment" value="1">
            
            <div class="mb-3">
                <label class="form-label">Update Status <span class="text-danger">*</span></label>
                <select name="status" class="form-select" id="popupStatus" required onchange="toggleSubStatus(this.value)">
                    <option value="">Select Status</option>
                    <?php if (count($all_statuses) > 0): ?>
                        <?php foreach ($all_statuses as $status): ?>
                            <option value="<?php echo htmlspecialchars($status['name']); ?>">
                                <?php echo htmlspecialchars($status['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <!-- Sub Status Container (Dynamically Shown/Hidden) -->
            <div class="sub-status-container" id="subStatusContainer">
                <label class="form-label">Sub Status <span class="sub-status-required" id="subStatusRequired" style="display: none;">*</span></label>
                <select name="sub_status" class="form-select" id="popupSubStatus">
                    <option value="">Select Sub Status</option>
                    <!-- Sub status options will be dynamically populated -->
                </select>
                <small class="form-text text-muted">Select a sub status for more specific tracking</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Follow-up Date & Time (Optional)</label>
                <input type="datetime-local" class="form-control" name="followup_date" id="followupDate">
                <div class="form-text">Leave empty if no follow-up needed</div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Comment <span class="text-danger">*</span></label>
                <textarea class="form-control" name="comment" rows="3" placeholder="Enter your comment..." required></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Update Status & Comment</button>
        </form>
        
        <div class="comments-history" id="commentsHistory">
            <!-- Comments will be loaded here via AJAX -->
        </div>
    </div>

    <!-- Single Comment Delete Popup -->
    <div class="delete-popup" id="commentDeletePopup">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Delete Comment</h5>
            <button type="button" class="btn-close" onclick="closeCommentDeletePopup()"></button>
        </div>
        
        <form method="POST" id="commentDeleteForm">
            <input type="hidden" name="comment_id" id="deleteCommentId">
            <input type="hidden" name="delete_comment" value="1">
            
            <div class="alert alert-warning">
                <strong>Warning:</strong> You are about to delete this comment. This action cannot be undone.
            </div>
            
            <div class="mb-3">
                <label class="form-label">Comment Text</label>
                <textarea class="form-control" id="deleteCommentText" rows="3" readonly></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Reason for Deletion <span class="text-danger">*</span></label>
                <textarea class="form-control" name="delete_reason" rows="3" placeholder="Please provide a reason for deleting this comment..." required></textarea>
                <div class="form-text">This reason will be recorded in the system logs.</div>
            </div>
            
            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" onclick="closeCommentDeletePopup()">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Comment</button>
            </div>
        </form>
    </div>

    <!-- Bulk Comment Delete Popup -->
    <div class="delete-popup" id="bulkCommentDeletePopup">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Delete Multiple Comments</h5>
            <button type="button" class="btn-close" onclick="closeBulkCommentDeletePopup()"></button>
        </div>
        
        <form method="POST" id="bulkCommentDeleteForm">
            <input type="hidden" name="bulk_delete_comments" value="1">
            
            <div class="alert alert-warning">
                <strong>Warning:</strong> You are about to delete <span id="selectedCommentsCount" class="fw-bold">0</span> comments. This action cannot be undone.
            </div>
            
            <div class="mb-3">
                <label class="form-label">Reason for Deletion <span class="text-danger">*</span></label>
                <textarea class="form-control" name="bulk_delete_reason" rows="3" placeholder="Please provide a reason for deleting these comments..." required></textarea>
                <div class="form-text">This reason will be recorded in the system logs for each deleted comment.</div>
            </div>
            
            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" onclick="closeBulkCommentDeletePopup()">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Selected Comments</button>
            </div>
        </form>
    </div>

    <!-- Delete History Popup -->
    <div class="delete-popup" id="deleteHistoryPopup" style="width: 800px; max-width: 95%;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="text-danger"><i class="fas fa-history me-2"></i>Comment Delete History</h5>
            <button type="button" class="btn-close" onclick="closeDeleteHistoryPopup()"></button>
        </div>
        
        <div id="deleteHistoryContent">
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading delete history...</p>
            </div>
        </div>
        
        <div class="mt-3 text-end">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteHistoryPopup()">Close</button>
        </div>
    </div>

    <!-- DataTables Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/select/1.7.0/js/dataTables.select.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    
    <script>
        // Status to Sub Status Mapping from PHP
        const statusSubStatusMap = <?php echo json_encode($status_substatus_map); ?>;

        // Function to update time displays in real-time
        function updateTimeDisplays() {
            const timeElements = document.querySelectorAll('.time-since-comment');
            const now = Math.floor(Date.now() / 1000); // Current time in seconds
            
            timeElements.forEach(element => {
                const title = element.getAttribute('title');
                if (title && title.includes('Last comment:')) {
                    // Extract timestamp from title
                    const timestampMatch = title.match(/Last comment: (.+)$/);
                    if (timestampMatch) {
                        const lastCommentTime = Math.floor(new Date(timestampMatch[1]).getTime() / 1000);
                        const timeDiff = now - lastCommentTime;
                        
                        let timeDisplay = '';
                        let timeClass = '';
                        
                        if (timeDiff < 3600) { // Less than 1 hour
                            const minutes = Math.floor(timeDiff / 60);
                            timeDisplay = minutes + ' min ago';
                            timeClass = (minutes > 30) ? 'time-urgent' : '';
                        } else if (timeDiff < 86400) { // Less than 1 day
                            const hours = Math.floor(timeDiff / 3600);
                            timeDisplay = hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
                            timeClass = (hours > 12) ? 'time-urgent' : '';
                        } else if (timeDiff < 2592000) { // Less than 30 days
                            const days = Math.floor(timeDiff / 86400);
                            timeDisplay = days + ' day' + (days > 1 ? 's' : '') + ' ago';
                            timeClass = (days > 7) ? 'time-critical' : '';
                        } else { // More than 30 days
                            const months = Math.floor(timeDiff / 2592000);
                            timeDisplay = months + ' month' + (months > 1 ? 's' : '') + ' ago';
                            timeClass = 'time-critical';
                        }
                        
                        // Update the element
                        element.innerHTML = '⏱️ ' + timeDisplay;
                        element.className = 'time-since-comment ' + timeClass;
                    }
                }
            });
        }

        // DataTable instance
        let dataTable;

        // Initialize DataTable with enhanced search functionality
        $(document).ready(function() {
            setInterval(updateTimeDisplays, 60000);

            dataTable = $('#leadsTable').DataTable({
                "pageLength": 25,
                "order": [[1, 'asc']],
                "columnDefs": [
                    { 
                        "orderable": false, 
                        "targets": [0, 2, 14, 15]
                    },
                    {
                        "searchable": true,
                        "targets": '_all'
                    }
                ],
                "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"t>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                "language": {
                    "search": "Global Search:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "infoEmpty": "Showing 0 to 0 of 0 entries",
                    "infoFiltered": "(filtered from _MAX_ total entries)",
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "Next",
                        "previous": "Previous"
                    }
                },
                "drawCallback": function(settings) {
                    $('.toggle-history').off('click').on('click', function() {
                        const leadId = $(this).closest('tr').data('lead-id');
                        toggleHistory(leadId);
                    });

                    // Update time displays after table redraw
                    setTimeout(updateTimeDisplays, 100);
                }
            });

            $('#selectAll').off('change').on('change', function() {
                $('.lead-checkbox').prop('checked', this.checked);
            });

            // Initialize filter sub status
            updateFilterSubStatus();
        });

        // Bulk Assign Functions
        function clearLeadIds() {
            document.getElementById('leadIdsInput').value = '';
            updateLeadIdsCounter();
        }

        function updateLeadIdsCounter() {
            const input = document.getElementById('leadIdsInput');
            const value = input.value.trim();
            const counterContainer = document.getElementById('leadIdsCounterContainer');
            
            if (!value) {
                counterContainer.innerHTML = '';
                return;
            }
            
            // Count unique lead IDs
            const leadIds = value.split(/[\s,\n]+/).filter(id => id.trim() !== '');
            const uniqueIds = [...new Set(leadIds)];
            
            // Show counter
            counterContainer.innerHTML = `
                <span class="badge bg-primary">
                    <i class="fas fa-hashtag me-1"></i>
                    ${uniqueIds.length} unique lead IDs
                </span>
            `;
        }

        // Function to open WhatsApp
        function openWhatsApp(phoneNumber) {
            // Remove any non-digit characters from phone number
            const cleanPhone = phoneNumber.replace(/\D/g, '');
            
            // Create WhatsApp URL
            const whatsappUrl = `https://wa.me/${cleanPhone}`;
            
            // Open WhatsApp in new tab
            window.open(whatsappUrl, '_blank');
        }

        // Function to export filtered data
        function exportFilteredData() {
            showDownloadStatus();
            
            // Get current filter values
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            const leadStatus = document.querySelector('select[name="lead_status"]').value;
            const subStatus = document.querySelector('select[name="sub_status"]').value;
            const assignedUser = document.querySelector('select[name="assigned_user"]').value;
            
            // NEW: Get comment date filters
            const commentStartDate = document.querySelector('input[name="comment_start_date"]').value;
            const commentEndDate = document.querySelector('input[name="comment_end_date"]').value;
            
            // Create a form and submit it to trigger the export
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'leads_management.php';
            
            // Add CSRF token if available
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'export_leads';
            csrfInput.value = '1';
            form.appendChild(csrfInput);
            
            // Add filter values
            const startDateInput = document.createElement('input');
            startDateInput.type = 'hidden';
            startDateInput.name = 'export_start_date';
            startDateInput.value = startDate;
            form.appendChild(startDateInput);
            
            const endDateInput = document.createElement('input');
            endDateInput.type = 'hidden';
            endDateInput.name = 'export_end_date';
            endDateInput.value = endDate;
            form.appendChild(endDateInput);
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'export_lead_status';
            statusInput.value = leadStatus;
            form.appendChild(statusInput);
            
            const assignedInput = document.createElement('input');
            assignedInput.type = 'hidden';
            assignedInput.name = 'export_assigned_user';
            assignedInput.value = assignedUser;
            form.appendChild(assignedInput);

            const subStatusInput = document.createElement('input');
            subStatusInput.type = 'hidden';
            subStatusInput.name = 'export_lead_sub_status';
            subStatusInput.value = subStatus;
            form.appendChild(subStatusInput);
            
            // NEW: Add comment date filters to export
            const commentStartInput = document.createElement('input');
            commentStartInput.type = 'hidden';
            commentStartInput.name = 'export_comment_start_date';
            commentStartInput.value = commentStartDate;
            form.appendChild(commentStartInput);
            
            const commentEndInput = document.createElement('input');
            commentEndInput.type = 'hidden';
            commentEndInput.name = 'export_comment_end_date';
            commentEndInput.value = commentEndDate;
            form.appendChild(commentEndInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // Function to filter by status
        function filterByStatus(status) {
            const url = new URL(window.location.href);
            
            if (status === 'all') {
                url.searchParams.delete('lead_status');
                url.searchParams.delete('sub_status'); // Clear sub_status when selecting all
            } else {
                url.searchParams.set('lead_status', status);
                // Clear sub_status when changing main status
                url.searchParams.delete('sub_status');
            }
            
            window.location.href = url.toString();
        }

        // Function to filter by assigned user
        function filterByUser(userId) {
            const url = new URL(window.location.href);
            
            if (userId === 'all') {
                url.searchParams.delete('assigned_user');
            } else {
                url.searchParams.set('assigned_user', userId);
            }
            
            window.location.href = url.toString();
        }

        function toggleHistory(leadId) {
            const historyRow = document.getElementById('historyRow' + leadId);
            const toggleBtn = document.querySelector(`[onclick="toggleHistory(${leadId})"]`);
            
            if (historyRow.style.display === 'none') {
                historyRow.style.display = 'table-row';
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="fas fa-history me-1"></i>Hide History';
                }
            } else {
                historyRow.style.display = 'none';
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="fas fa-history me-1"></i>View History';
                }
            }
        }

        function showDownloadStatus() {
            const downloadStatus = document.getElementById('downloadStatus');
            const statusMessage = document.getElementById('statusMessage');
            
            statusMessage.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Preparing your download...';
            downloadStatus.style.display = 'block';
            
            // Hide after 10 seconds if still showing
            setTimeout(() => {
                if (downloadStatus.style.display === 'block') {
                    downloadStatus.style.display = 'none';
                }
            }, 10000);
        }

        function hideDownloadStatus() {
            document.getElementById('downloadStatus').style.display = 'none';
        }

        function selectLeads() {
            const selectCount = document.getElementById('selectCount').value;
            const checkboxes = document.querySelectorAll('.lead-checkbox');
            
            // Uncheck all first
            checkboxes.forEach(checkbox => checkbox.checked = false);
            
            if (selectCount === 'all') {
                // Select all checkboxes
                checkboxes.forEach(checkbox => checkbox.checked = true);
            } else {
                // Select specific number of checkboxes
                const count = parseInt(selectCount);
                for (let i = 0; i < Math.min(count, checkboxes.length); i++) {
                    checkboxes[i].checked = true;
                }
            }
            
            // Update select all checkbox
            updateSelectAllCheckbox();
        }

        function updateSelectAllCheckbox() {
            const checkboxes = document.querySelectorAll('.lead-checkbox');
            const selectAll = document.getElementById('selectAll');
            const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            
            if (checkedCount === 0) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            } else if (checkedCount === checkboxes.length) {
                selectAll.checked = true;
                selectAll.indeterminate = false;
            } else {
                selectAll.checked = false;
                selectAll.indeterminate = true;
            }
        }

        // Comment Popup Functions - UPDATED VERSION
        function openCommentPopup(leadId, currentStatus, currentSubStatus = '') {
            document.getElementById('popupLeadId').value = leadId;
            document.getElementById('popupStatus').value = currentStatus;
            
            // Set current sub status if exists
            if (currentSubStatus) {
                document.getElementById('popupSubStatus').value = currentSubStatus;
            }
            
            document.getElementById('commentPopup').style.display = 'block';
            document.getElementById('overlay').style.display = 'block';
            
            // Immediately set sub status visibility based on current status
            toggleSubStatus(currentStatus);
            
            // Load comments history via AJAX
            loadCommentsHistory(leadId);
        }

        function closeCommentPopup() {
            document.getElementById('commentPopup').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
            // Reset sub status container
            document.getElementById('subStatusContainer').style.display = 'none';
            document.getElementById('popupSubStatus').value = '';
            document.getElementById('subStatusRequired').style.display = 'none';
            document.getElementById('popupSubStatus').required = false;
        }

        function loadCommentsHistory(leadId) {
            const commentsHistory = document.getElementById('commentsHistory');
            commentsHistory.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Loading comments...</div>';
            
            // Create AJAX request to fetch comments
            const xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    commentsHistory.innerHTML = xhr.responseText;
                }
            };
            
            xhr.open('GET', 'get_comments.php?lead_id=' + leadId, true);
            xhr.send();
        }

        // Toggle Sub Status based on selected status - UPDATED VERSION
        function toggleSubStatus(selectedStatus) {
            const subStatusContainer = document.getElementById('subStatusContainer');
            const subStatusSelect = document.getElementById('popupSubStatus');
            const subStatusRequired = document.getElementById('subStatusRequired');
            
            // Clear existing options except the first one
            while (subStatusSelect.options.length > 1) {
                subStatusSelect.remove(1);
            }
            
            // Check if this status has sub statuses
            const statusData = statusSubStatusMap[selectedStatus];
            
            if (statusData && statusData.has_sub_status && statusData.sub_statuses.length > 0) {
                // Get current sub_status from URL parameters
                const urlParams = new URLSearchParams(window.location.search);
                const currentSubStatus = urlParams.get('sub_status') || '';
                
                // Add sub status options
                statusData.sub_statuses.forEach(subStatus => {
                    const option = document.createElement('option');
                    option.value = subStatus.name;
                    option.textContent = subStatus.name;

                    if (currentSubStatus === subStatus.name) {
                        option.selected = true;
                    }
                    subStatusSelect.appendChild(option);
                });
                
                subStatusContainer.style.display = 'block';
                subStatusSelect.required = true;
                subStatusRequired.style.display = 'inline';
            } else {
                subStatusContainer.style.display = 'none';
                subStatusSelect.required = false;
                subStatusRequired.style.display = 'none';
                subStatusSelect.value = '';
            }
        }

        // Function to update filter sub status based on selected status
        function updateFilterSubStatus() {
            const statusSelect = document.getElementById('filterLeadStatus');
            const subStatusSelect = document.getElementById('filterSubStatus');
            const selectedStatus = statusSelect.value;
            // Get current sub_status from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const currentSubStatus = urlParams.get('sub_status') || '';
            
            // Clear existing options except the first one
            while (subStatusSelect.options.length > 1) {
                subStatusSelect.remove(1);
            }
            
            if (selectedStatus !== 'all') {
                const statusData = statusSubStatusMap[selectedStatus];
                
                if (statusData && statusData.has_sub_status && statusData.sub_statuses.length > 0) {
                    statusData.sub_statuses.forEach(subStatus => {
                        const option = document.createElement('option');
                        if (currentSubStatus === subStatus.name) {
                            option.selected = true;
                        }
                    
                        option.value = subStatus.name;
                        option.textContent = subStatus.name;
                        subStatusSelect.appendChild(option);
                    });
                }
            }
        }

        // Comment Delete Functions
        function openCommentDeletePopup(commentId, commentText) {
            document.getElementById('deleteCommentId').value = commentId;
            document.getElementById('deleteCommentText').value = commentText;
            document.getElementById('commentDeletePopup').style.display = 'block';
            document.getElementById('overlay').style.display = 'block';
        }

        function closeCommentDeletePopup() {
            document.getElementById('commentDeletePopup').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
        }

        // Bulk Comment Delete Functions
        function openBulkCommentDeletePopup() {
            const selectedComments = document.querySelectorAll('.comment-checkbox:checked');
            const selectedCount = selectedComments.length;
            
            if (selectedCount === 0) {
                alert('Please select at least one comment to delete.');
                return;
            }
            
            document.getElementById('selectedCommentsCount').textContent = selectedCount;
            document.getElementById('bulkCommentDeletePopup').style.display = 'block';
            document.getElementById('overlay').style.display = 'block';
        }

        function closeBulkCommentDeletePopup() {
            document.getElementById('bulkCommentDeletePopup').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
        }

        function selectAllComments() {
            const commentCheckboxes = document.querySelectorAll('.comment-checkbox');
            commentCheckboxes.forEach(checkbox => {
                checkbox.style.display = 'inline-block';
                checkbox.checked = true;
            });
            
            // Show delete buttons
            const deleteButtons = document.querySelectorAll('.comment-delete-btn');
            deleteButtons.forEach(button => button.style.display = 'flex');
        }

        function deselectAllComments() {
            const commentCheckboxes = document.querySelectorAll('.comment-checkbox');
            commentCheckboxes.forEach(checkbox => {
                checkbox.style.display = 'none';
                checkbox.checked = false;
            });
            
            // Hide delete buttons
            const deleteButtons = document.querySelectorAll('.comment-delete-btn');
            deleteButtons.forEach(button => button.style.display = 'none');
        }

        // Delete History Functions
        function viewDeleteHistory(leadId) {
            document.getElementById('deleteHistoryPopup').style.display = 'block';
            document.getElementById('overlay').style.display = 'block';
            
            // Load delete history via AJAX
            loadDeleteHistory(leadId);
        }

        function closeDeleteHistoryPopup() {
            document.getElementById('deleteHistoryPopup').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
        }

        function loadDeleteHistory(leadId) {
            const deleteHistoryContent = document.getElementById('deleteHistoryContent');
            
            // Show loading
            deleteHistoryContent.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading delete history...</p>
                </div>
            `;
            
            // Make AJAX request to fetch delete history
            fetch(`leads_management.php?view_delete_history=1&lead_id=${leadId}`)
                .then(response => response.text())
                .then(data => {
                    deleteHistoryContent.innerHTML = data;
                })
                .catch(error => {
                    deleteHistoryContent.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading delete history: ${error}
                        </div>
                    `;
                });
        }

        function closeAllPopups() {
            closeCommentPopup();
            closeCommentDeletePopup();
            closeBulkCommentDeletePopup();
            closeDeleteHistoryPopup();
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Update select all checkbox when individual checkboxes change
            document.querySelectorAll('.lead-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectAllCheckbox);
            });
            
            // Handle select all checkbox
            document.getElementById('selectAll').addEventListener('change', function() {
                document.querySelectorAll('.lead-checkbox').forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
            
            // Show comment checkboxes on hover
            document.querySelectorAll('.comment-text-only').forEach(commentDiv => {
                commentDiv.addEventListener('mouseenter', function() {
                    const checkbox = this.querySelector('.comment-checkbox');
                    const deleteBtn = this.querySelector('.comment-delete-btn');
                    if (checkbox) {
                        checkbox.style.display = 'inline-block';
                    }
                    if (deleteBtn) {
                        deleteBtn.style.display = 'flex';
                    }
                });
                
                commentDiv.addEventListener('mouseleave', function() {
                    const checkbox = this.querySelector('.comment-checkbox');
                    const deleteBtn = this.querySelector('.comment-delete-btn');
                    if (checkbox && !checkbox.checked) {
                        checkbox.style.display = 'none';
                    }
                    if (deleteBtn && !checkbox.checked) {
                        deleteBtn.style.display = 'none';
                    }
                });
            });
            
            // Prevent comment checkboxes from closing when clicking on them
            document.querySelectorAll('.comment-checkbox').forEach(checkbox => {
                checkbox.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllPopups();
            }
        });

        // Export form handling
        document.getElementById('exportForm').addEventListener('submit', function() {
            showDownloadStatus();
        });

        // Show success message when download starts (this would typically be handled by the server)
        window.addEventListener('beforeunload', function() {
            hideDownloadStatus();
        });
        function quickStatusUpdate(leadId, status) {
            if (confirm(`Are you sure you want to change status to "${status}"?`)) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'leads_management.php';
                
                const leadIdInput = document.createElement('input');
                leadIdInput.type = 'hidden';
                leadIdInput.name = 'lead_id';
                leadIdInput.value = leadId;
                form.appendChild(leadIdInput);
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status';
                statusInput.value = status;
                form.appendChild(statusInput);
                
                const commentInput = document.createElement('input');
                commentInput.type = 'hidden';
                commentInput.name = 'comment';
                commentInput.value = `Status changed to ${status} via quick action`;
                form.appendChild(commentInput);
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'add_comment';
                actionInput.value = '1';
                form.appendChild(actionInput);
                
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            }
        }
        // Event Listeners for comment checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            // Show comment checkboxes on hover
            document.querySelectorAll('.comment-text-only').forEach(commentDiv => {
                commentDiv.addEventListener('mouseenter', function() {
                    const checkbox = this.querySelector('.comment-checkbox');
                    const deleteBtn = this.querySelector('.comment-delete-btn');
                    if (checkbox) checkbox.style.display = 'inline-block';
                    if (deleteBtn) deleteBtn.style.display = 'flex';
                });

                commentDiv.addEventListener('mouseleave', function() {
                    const checkbox = this.querySelector('.comment-checkbox');
                    const deleteBtn = this.querySelector('.comment-delete-btn');
                    if (checkbox && !checkbox.checked) {
                        checkbox.style.display = 'none';
                    }
                    if (deleteBtn && !checkbox.checked) {
                        deleteBtn.style.display = 'none';
                    }
                });
            });

            // Handle comment checkbox changes
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('comment-checkbox')) {
                    const deleteBtn = e.target.closest('.comment-text-only').querySelector('.comment-delete-btn');
                    if (e.target.checked) {
                        deleteBtn.style.display = 'flex';
                    } else {
                        deleteBtn.style.display = 'none';
                    }
                }
            });

            // Handle bulk comment delete form submission
            document.getElementById('bulkCommentDeleteForm').addEventListener('submit', function(e) {
                const selectedComments = document.querySelectorAll('.comment-checkbox:checked');
                if (selectedComments.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one comment to delete.');
                    return;
                }

                // Add selected comment IDs to form
                selectedComments.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'comment_ids[]';
                    input.value = checkbox.value;
                    this.appendChild(input);
                });
            });

            // Handle form submissions
            document.getElementById('assignForm').addEventListener('submit', function(e) {
                const selectedLeads = document.querySelectorAll('.lead-checkbox:checked');
                if (selectedLeads.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one lead to assign.');
                    return;
                }
                
                const userId = document.getElementById('assignUserId').value;
                if (!userId) {
                    e.preventDefault();
                    alert('Please select a user to assign leads to.');
                    return;
                }
            });

            // Add keyboard event listeners for ESC key to close popups
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeAllPopups();
                }
            });

            // Initialize the page completely
            updateTimeDisplays();
            updateLeadIdsCounter();
            
            console.log('Leads Management Super Admin page initialized successfully');
        });
    </script>
</body>
</html>