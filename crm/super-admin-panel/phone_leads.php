<?php
include_once __DIR__ .'/common/db_connection.php';
if (!isset($_SESSION['super_admin_user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle Comment and Status Submission
if (isset($_POST['add_comment'])) {
    $lead_id = mysqli_real_escape_string($conn, $_POST['lead_id']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Follow-up date is optional
    $followup_date = !empty($_POST['followup_date']) ? mysqli_real_escape_string($conn, $_POST['followup_date']) : null;
    
    // Update lead status
    $update_status = "UPDATE leads SET status = '$status', updated_at = NOW() WHERE id = '$lead_id'";
    
    // Insert comment
    $insert_comment = "INSERT INTO comments (leadid, comments, datetime, created_by) 
                      VALUES ('$lead_id', '$comment', NOW(), '{$_SESSION['super_admin_user_id']}')";
    
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
        
        // If follow-up date is provided, update it in leads table
        if ($followup_date) {
            $update_followup = "UPDATE leads SET followup_date = '$followup_date' WHERE id = '$lead_id'";
            if (!mysqli_query($conn, $update_followup)) {
                throw new Exception("Error updating follow-up date: " . mysqli_error($conn));
            }
        }
        
        // Commit transaction
        mysqli_commit($conn);
        $_SESSION['message'] = "Status updated and comment added successfully!";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: phone_leads.php?phone=" . urlencode($_GET['phone']));
    exit;
}

// Get phone number from URL
$phone = $_GET['phone'] ?? '';
if (empty($phone)) {
    header("Location: leads.php");
    exit;
}

// Fetch all leads with this phone number
$leads_query = "SELECT l.*, u.name as assigned_user_name 
                FROM leads l 
                LEFT JOIN users u ON l.assigned_to = u.id 
                WHERE l.phoneno = '" . mysqli_real_escape_string($conn, $phone) . "' 
                AND l.is_deleted = 0 
                ORDER BY l.created_at DESC";
$leads_result = mysqli_query($conn, $leads_query);

// Count total leads with this phone
$total_leads = mysqli_num_rows($leads_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads for Phone: <?php echo htmlspecialchars($phone); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .phone-header {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .badge-hot { background-color: #dc3545; }
        .badge-warm { background-color: #fd7e14; }
        .badge-cold { background-color: #0dcaf0; }
        .badge-new { background-color: #0d6efd; }
        .badge-contacted { background-color: #fd7e14; }
        .badge-follow_up { background-color: #ffc107; color: #000; }
        .badge-confirmed { background-color: #198754; }
        .badge-converted { background-color: #20c997; }
        .badge-rejected { background-color: #dc3545; }
        .badge-assigned { background-color: #6f42c1; }
        .status-badge {
            font-size: 0.8em;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .back-btn:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }
        .comment-popup {
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
        }
        .comment-date {
            color: #666;
            font-size: 12px;
        }
        .comment-user {
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include('common/header.php'); ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include('common/sidebar.php'); ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <a href="leads.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Leads
                    </a>
                </div>

                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="phone-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-1">
                                <i class="fas fa-phone me-2"></i>
                                Phone: <?php echo htmlspecialchars($phone); ?>
                            </h2>
                            <p class="mb-0">Total Leads: <?php echo $total_leads; ?></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="bg-white text-dark p-3 rounded">
                                <h4 class="mb-0"><?php echo $total_leads; ?> Leads</h4>
                                <small>Total Records</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="phoneLeadsTable" class="table table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>S.No</th>
                                        <th>Lead Id</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Type</th>
                                        <th>Assigned To</th>
                                        <th>Address</th>
                                        <th>City</th>
                                        <th>State</th>
                                        <th>Category</th>
                                        <th>Created Date</th>
                                        <th>Last Updated</th>
                                        <th>Comments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sno = 1;
                                    if ($leads_result && mysqli_num_rows($leads_result) > 0):
                                        while ($lead = mysqli_fetch_assoc($leads_result)):
                                            
                                            // Fetch comments for this lead
                                            $comments_query = "SELECT c.*, u.name as user_name 
                                                             FROM comments c 
                                                             LEFT JOIN users u ON c.created_by = u.id 
                                                             WHERE c.leadid = '{$lead['id']}' 
                                                             AND c.is_deleted = 0 
                                                             ORDER BY c.datetime DESC";
                                            $comments_result = mysqli_query($conn, $comments_query);
                                            $comments = [];
                                            if ($comments_result) {
                                                $comments = mysqli_fetch_all($comments_result, MYSQLI_ASSOC);
                                            }
                                    ?>
                                        <tr>
                                            <td><?php echo $sno++; ?></td>
                                            <td><?php echo htmlspecialchars($lead['id']); ?></td>
                                            <td><?php echo htmlspecialchars($lead['name']); ?></td>
                                            <td><?php echo htmlspecialchars($lead['email']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $lead['status']; ?> status-badge">
                                                    <?php echo htmlspecialchars(ucfirst($lead['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($lead['type'])): ?>
                                                    <span class="badge badge-<?php echo $lead['type']; ?> status-badge">
                                                        <?php echo htmlspecialchars(ucfirst($lead['type'])); ?>
                                                    </span>
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
                                            <td><?php echo htmlspecialchars($lead['address']); ?></td>
                                            <td><?php echo htmlspecialchars($lead['city']); ?></td>
                                            <td><?php echo htmlspecialchars($lead['state']); ?></td>
                                            <td><?php echo htmlspecialchars($lead['category']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($lead['created_at'])); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($lead['updated_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="openCommentPopup(<?php echo $lead['id']; ?>, '<?php echo $lead['status']; ?>')">
                                                    Add Comment (<?php echo count($comments); ?>)
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="14" class="text-center">No leads found for this phone number.</td>
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
    <div class="overlay" id="overlay" onclick="closeCommentPopup()"></div>
    <div class="comment-popup" id="commentPopup">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5>Update Status & Add Comment</h5>
            <button type="button" class="btn-close" onclick="closeCommentPopup()"></button>
        </div>
        
        <form method="POST" id="commentForm">
            <input type="hidden" name="lead_id" id="popupLeadId">
            <input type="hidden" name="add_comment" value="1">
            
            <div class="mb-3">
                <label class="form-label">Update Status</label>
                <select name="status" class="form-select" id="popupStatus" required>
                    <option value="new">New</option>
                    <option value="processing">processing</option>
                    <option value="duplicate">duplicate</option>
                    <option value="follow_up">Follow Up</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="converted">Converted</option>
                    <option value="rejected">Rejected</option>
                </select>
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

    <!-- DataTables Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#phoneLeadsTable').DataTable({
                "pageLength": 25,
                "order": [[0, 'asc']],
                "language": {
                    "search": "Search:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "infoEmpty": "Showing 0 to 0 of 0 entries",
                    "infoFiltered": "(filtered from _MAX_ total entries)"
                }
            });
        });

        function openCommentPopup(leadId, currentStatus) {
            document.getElementById('popupLeadId').value = leadId;
            document.getElementById('popupStatus').value = currentStatus;
            document.getElementById('followupDate').value = ''; // Clear follow-up date
            document.getElementById('overlay').style.display = 'block';
            document.getElementById('commentPopup').style.display = 'block';
            
            // Load comments for this lead
            loadComments(leadId);
        }
        
        function closeCommentPopup() {
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('commentPopup').style.display = 'none';
        }
        
        function loadComments(leadId) {
            const commentsHistory = document.getElementById('commentsHistory');
            commentsHistory.innerHTML = '<div class="text-center">Loading comments...</div>';
            
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
        
        // Close popup when clicking outside
        document.getElementById('overlay').addEventListener('click', closeCommentPopup);
        
        // Prevent form submission from closing popup immediately
        document.getElementById('commentForm').addEventListener('submit', function(e) {
            setTimeout(function() {
                closeCommentPopup();
            }, 1000);
        });
    </script>
</body>
</html>