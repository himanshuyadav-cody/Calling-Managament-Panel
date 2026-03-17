<?php
include_once __DIR__ .'/common/db_connection.php';

// Security check - only allow authenticated super admin users
if (!isset($_SESSION['super_admin_user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    echo "<div class='text-danger'>Unauthorized access</div>";
    exit;
}

if (isset($_GET['lead_id'])) {
    $lead_id = mysqli_real_escape_string($conn, $_GET['lead_id']);
    
    $comments_query = "SELECT c.*, u.name as user_name 
                      FROM comments c 
                      LEFT JOIN users u ON c.created_by = u.id 
                      WHERE c.leadid = '$lead_id' AND c.is_deleted = 0
                      ORDER BY c.datetime DESC 
                      LIMIT 10"; // Limit to 10 latest comments
    $comments_result = mysqli_query($conn, $comments_query);
    
    if ($comments_result && mysqli_num_rows($comments_result) > 0) {
        echo '<h6>Recent Comments:</h6>';
        echo '<div class="comments-list" style="max-height: 200px; overflow-y: auto;">';
        while ($comment = mysqli_fetch_assoc($comments_result)) {
            echo '<div class="comment-item mb-2 p-2 border rounded">';
            echo '<div class="d-flex justify-content-between align-items-start">';
            echo '<div class="comment-text flex-grow-1">';
            echo '<strong class="text-primary">' . htmlspecialchars($comment['user_name'] ?: 'System') . ':</strong><br>';
            echo '<span class="text-dark">' . htmlspecialchars($comment['comments']) . '</span>';
            echo '</div>';
            echo '</div>';
            echo '<div class="comment-date text-muted small mt-1">';
            echo '<i class="fas fa-clock me-1"></i>' . date('M j, Y g:i A', strtotime($comment['datetime']));
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="text-muted text-center p-3">No comments found for this lead.</div>';
    }
} else {
    echo '<div class="text-danger">Lead ID not provided.</div>';
}

// Close database connection
mysqli_close($conn);
?>