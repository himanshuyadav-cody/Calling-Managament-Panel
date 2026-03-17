<?php
include_once __DIR__ .'/common/db_connection.php';

if (isset($_GET['lead_id'])) {
    $lead_id = mysqli_real_escape_string($conn, $_GET['lead_id']);
    
    $comments_query = "SELECT c.*, u.name as user_name 
                      FROM comments c 
                      LEFT JOIN users u ON c.created_by = u.id 
                      WHERE c.leadid = '$lead_id' AND c.is_deleted = 0 
                      ORDER BY c.datetime DESC";
    $comments_result = mysqli_query($conn, $comments_query);
    
    if (mysqli_num_rows($comments_result) > 0) {
        echo '<h6>Comment History:</h6>';
        while ($comment = mysqli_fetch_assoc($comments_result)) {
            echo '<div class="comment-item">';
            echo '<div class="comment-text"><strong>' . htmlspecialchars($comment['user_name'] ?: 'System') . ':</strong> ' . htmlspecialchars($comment['comments']) . '</div>';
            echo '<div class="comment-date">' . date('M j, Y g:i A', strtotime($comment['datetime'])) . '</div>';
            echo '</div>';
        }
    } else {
        echo '<div class="text-muted">No comments yet.</div>';
    }
}
?>