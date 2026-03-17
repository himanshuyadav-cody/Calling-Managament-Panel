<?php
include_once __DIR__ .'/common/db_connection.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Get user details from database
$user_query = "SELECT name FROM users WHERE id = '$userId'";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
$user_name = $user_data['name'] ?? 'User';

// Store in session for use in header
$_SESSION['user_name'] = $user_name;

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

// Get filter values
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$lead_type = $_GET['lead_type'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$sub_status_filter = $_GET['sub_status'] ?? 'all';
$comment_start_date = $_GET['comment_start_date'] ?? '';
$comment_end_date = $_GET['comment_end_date'] ?? '';

// Build base query for leads with filters
$leads_base_query = "SELECT * FROM leads WHERE is_deleted = 0 AND assigned_to = $userId";

// Build base query for counts (same filters applied)
$counts_base_query = "SELECT COUNT(*) as total_count FROM leads WHERE is_deleted = 0 AND assigned_to = $userId";

// Add filters to both queries
$filter_conditions = [];

// Add status filter
if (!empty($status_filter) && $status_filter != 'all') {
    $status_condition = "status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
    $filter_conditions[] = $status_condition;
}

// Add sub status filter
if (!empty($sub_status_filter) && $sub_status_filter != 'all') {
    $sub_status_condition = "sub_status = '" . mysqli_real_escape_string($conn, $sub_status_filter) . "'";
    $filter_conditions[] = $sub_status_condition;
}

// Add date filters
if (!empty($start_date)) {
    $date_condition = "DATE(created_at) >= '" . mysqli_real_escape_string($conn, $start_date) . "'";
    $filter_conditions[] = $date_condition;
}
if (!empty($end_date)) {
    $date_condition = "DATE(created_at) <= '" . mysqli_real_escape_string($conn, $end_date) . "'";
    $filter_conditions[] = $date_condition;
}

// COMMENT DATE FILTER - This is complex so we'll handle separately
$comment_date_condition = "";
if (!empty($comment_start_date) || !empty($comment_end_date)) {
    $comment_date_condition = " AND id IN (
        SELECT DISTINCT c.leadid 
        FROM comments c 
        WHERE c.is_deleted = 0";
    
    if (!empty($comment_start_date)) {
        $comment_date_condition .= " AND DATE(c.datetime) >= '" . mysqli_real_escape_string($conn, $comment_start_date) . "'";
    }
    if (!empty($comment_end_date)) {
        $comment_date_condition .= " AND DATE(c.datetime) <= '" . mysqli_real_escape_string($conn, $comment_end_date) . "'";
    }
    
    $comment_date_condition .= ")";
}

// Apply filters to leads query
$leads_query = $leads_base_query;
if (!empty($filter_conditions)) {
    $leads_query .= " AND " . implode(" AND ", $filter_conditions);
}
$leads_query .= $comment_date_condition;
$leads_query .= " ORDER BY created_at DESC, updated_at ASC";

// Apply same filters to counts query
$counts_query = $counts_base_query;
if (!empty($filter_conditions)) {
    $counts_query .= " AND " . implode(" AND ", $filter_conditions);
}
$counts_query .= $comment_date_condition;

// Execute leads query
$leads_result = mysqli_query($conn, $leads_query);

// Store all leads data for duplicate checking and time display
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

// Get total count with applied filters
$total_count_result = mysqli_query($conn, $counts_query);
$total_leads = 0;
if ($total_count_result) {
    $total_count_data = mysqli_fetch_assoc($total_count_result);
    $total_leads = $total_count_data['total_count'];
}

// Get status counts with applied filters
$status_counts_query = "SELECT status, COUNT(*) as count 
                       FROM leads 
                       WHERE is_deleted = 0 AND assigned_to = $userId";

// Apply same filters to status counts
if (!empty($filter_conditions)) {
    $status_counts_query .= " AND " . implode(" AND ", $filter_conditions);
}
$status_counts_query .= $comment_date_condition;
$status_counts_query .= " GROUP BY status";

$status_counts_result = mysqli_query($conn, $status_counts_query);
$status_counts = [];
if ($status_counts_result) {
    while ($row = mysqli_fetch_assoc($status_counts_result)) {
        $status_counts[$row['status']] = $row['count'];
    }
}

// Get duplicate phone numbers for highlighting (with applied filters)
$duplicate_phones_query = "
    SELECT phoneno, COUNT(*) as count 
    FROM leads 
    WHERE is_deleted = 0 AND assigned_to = $userId
";

// Apply same filters to duplicate query
if (!empty($filter_conditions)) {
    $duplicate_phones_query .= " AND " . implode(" AND ", $filter_conditions);
}
$duplicate_phones_query .= $comment_date_condition;
$duplicate_phones_query .= " GROUP BY phoneno HAVING COUNT(*) > 1";

$duplicate_phones_result = mysqli_query($conn, $duplicate_phones_query);
$duplicate_phones = [];
if ($duplicate_phones_result && mysqli_num_rows($duplicate_phones_result) > 0) {
    while ($row = mysqli_fetch_assoc($duplicate_phones_result)) {
        $duplicate_phones[$row['phoneno']] = $row['count'];
    }
}

// Show filter summary
$filter_summary = "";
if (!empty($start_date) || !empty($end_date) || !empty($status_filter) || !empty($sub_status_filter) || !empty($comment_start_date) || !empty($comment_end_date)) {
    $filter_summary = "Applied Filters: ";
    $filters = [];
    
    if (!empty($start_date)) $filters[] = "Lead Created From: " . date('d-m-Y', strtotime($start_date));
    if (!empty($end_date)) $filters[] = "Lead Created To: " . date('d-m-Y', strtotime($end_date));
    if (!empty($status_filter) && $status_filter != 'all') $filters[] = "Status: " . ucfirst($status_filter);
    if (!empty($sub_status_filter) && $sub_status_filter != 'all') $filters[] = "Sub Status: " . ucfirst($sub_status_filter);
    
    if (!empty($comment_start_date)) $filters[] = "Comment From: " . date('d-m-Y', strtotime($comment_start_date));
    if (!empty($comment_end_date)) $filters[] = "Comment To: " . date('d-m-Y', strtotime($comment_end_date));
    
    $filter_summary .= implode(", ", $filters);
}

// Get all available statuses for the filter dropdown
$available_statuses = [];
$status_dropdown_query = "SELECT DISTINCT status FROM leads WHERE is_deleted = 0 AND assigned_to = $userId AND status IS NOT NULL ORDER BY status";
$status_dropdown_result = mysqli_query($conn, $status_dropdown_query);
if ($status_dropdown_result) {
    while ($row = mysqli_fetch_assoc($status_dropdown_result)) {
        $available_statuses[] = $row['status'];
    }
}

// Get all available sub statuses for the filter dropdown
$available_sub_statuses = [];
$sub_status_dropdown_query = "SELECT DISTINCT sub_status FROM leads WHERE is_deleted = 0 AND assigned_to = $userId AND sub_status IS NOT NULL ORDER BY sub_status";
$sub_status_dropdown_result = mysqli_query($conn, $sub_status_dropdown_query);
if ($sub_status_dropdown_result) {
    while ($row = mysqli_fetch_assoc($sub_status_dropdown_result)) {
        $available_sub_statuses[] = $row['sub_status'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Leads Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        /* Previous CSS styles remain the same */
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
        .badge-hot { background-color: #dc3545; }
        .badge-warm { background-color: #fd7e14; }
        .badge-cold { background-color: #0dcaf0; }
        .badge-new { background-color: #0d6efd; }
        .badge-processing { background-color: #6f42c1; }
        .badge-duplicate { background-color: #6c757d; }
        .badge-contacted { background-color: #fd7e14; }
        .badge-follow_up { background-color: #ffc107; color: #000; }
        .badge-confirmed { background-color: #198754; }
        .badge-converted { background-color: #20c997; }
        .badge-rejected { background-color: #dc3545; }
        .badge-trash { background-color: #6c757d; }
        .status-badge {
            font-size: 0.8em;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
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
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .status-filter-badge {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .status-filter-badge:hover {
            transform: scale(1.05);
        }
        .status-active {
            box-shadow: 0 0 0 3px rgba(255,255,255,0.5);
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        .welcome-text {
            color: #fff;
            margin-right: 15px;
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
        .filter-summary {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 14px;
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
        .no-comments {
            color: #6c757d;
            font-style: italic;
            text-align: center;
            padding: 10px;
        }
        .badge-proccessing{
            color: grey;
        }

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

        .custom-alert {
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        tr {
            transition: all 0.3s ease;
        }

        tr.moving {
            background-color: #e3f2fd !important;
        }

        .filter-badge-active {
            border: 2px solid #fff;
            box-shadow: 0 0 10px rgba(255,255,255,0.5);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <strong>Zapron</strong>
            </a>
            
            <div class="d-flex align-items-center">
                <span class="navbar-text me-3 welcome-text">
                    Welcome, <strong><?php echo htmlspecialchars($user_name); ?></strong>
                </span>
                
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user me-2"></i>Account
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include('common/sidebar.php'); ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 mt-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h2>All Leads Management</h2>
                    <?php if (!empty($duplicate_phones)): ?>
                        <div class="alert alert-warning d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <span><?php echo count($duplicate_phones); ?> duplicate phone numbers found</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Filter Summary -->
                <?php if (!empty($filter_summary)): ?>
                <div class="filter-summary">
                    <i class="fas fa-filter me-2"></i><?php echo $filter_summary; ?>
                    <span class="badge bg-primary ms-2"><?php echo count($all_leads_data); ?> leads found</span>
                </div>
                <?php endif; ?>

                <!-- Status Statistics - UPDATED WITH FILTERED COUNTS -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="stats-card">
                            <h5 class="mb-3">Leads Overview <small class="text-light">(Filtered Results)</small></h5>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="status-filter-badge badge bg-primary <?php echo $status_filter == 'all' ? 'filter-badge-active' : ''; ?>" 
                                      onclick="filterByStatus('all')">
                                    All Leads: <?php echo $total_leads; ?>
                                </span>
                                <?php 
                                // Show counts for all available statuses
                                foreach ($available_statuses as $status): 
                                    $count = $status_counts[$status] ?? 0;
                                    $is_active = ($status_filter == $status);
                                ?>
                                    <span class="status-filter-badge badge badge-<?php echo strtolower(str_replace(' ', '_', $status)); ?> <?php echo $is_active ? 'filter-badge-active' : ''; ?>" 
                                          onclick="filterByStatus('<?php echo $status; ?>')">
                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>: <?php echo $count; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Duplicate Phone Alert -->
                <?php if (!empty($duplicate_phones)): ?>
                <div class="duplicate-alert">
                    <h6><i class="fas fa-info-circle me-2"></i>Duplicate Phone Numbers Detected</h6>
                    <p class="mb-1">The following phone numbers have multiple leads:</p>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($duplicate_phones as $phone => $count): ?>
                            <span class="badge bg-danger">
                                <?php echo htmlspecialchars($phone); ?> 
                                <span class="badge bg-light text-dark"><?php echo $count; ?> leads</span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filters Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="filterLeadStatus" onchange="updateFilterSubStatus()">
                                <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Statuses</option>
                                <?php foreach ($available_statuses as $status): ?>
                                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo ($status_filter == $status) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($status)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sub Status</label>
                            <select class="form-select" name="sub_status" id="filterSubStatus">
                                <option value="all">All Sub Status</option>
                                <?php foreach ($available_sub_statuses as $sub_status): ?>
                                    <option value="<?php echo htmlspecialchars($sub_status); ?>" <?php echo ($sub_status_filter == $sub_status) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($sub_status)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Start Date (Lead Created)</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">End Date (Lead Created)</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Comment Start Date</label>
                            <input type="date" class="form-control" name="comment_start_date" value="<?php echo htmlspecialchars($comment_start_date); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Comment End Date</label>
                            <input type="date" class="form-control" name="comment_end_date" value="<?php echo htmlspecialchars($comment_end_date); ?>">
                        </div>
                        
                        <div class="col-md-12 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                            <a href="leads_management.php" class="btn btn-secondary">Clear All</a>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="leadsTable" class="table table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>S.No</th>
                                        <th>Lead Id</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Address</th>
                                        <th>City</th>
                                        <th>State</th>
                                        <th>Pincode</th>
                                        <th>Category</th>
                                        <th>Follow Up Date Time</th>
                                        <th>Created Date</th>
                                        <th>Comments</th>
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
                                            
                                            // Fetch comments for this lead
                                            $comments_query = "SELECT c.*, u.name as user_name 
                                                              FROM comments c 
                                                              LEFT JOIN users u ON c.created_by = u.id 
                                                              WHERE c.leadid = '{$lead['id']}'";
                                            
                                            if ($has_soft_delete) {
                                                $comments_query .= " AND c.is_deleted = 0";
                                            }
                                            
                                            $comments_query .= " ORDER BY c.datetime DESC LIMIT 5";
                                            
                                            $comments_result = mysqli_query($conn, $comments_query);
                                            $comments = [];
                                            if ($comments_result) {
                                                $comments = mysqli_fetch_all($comments_result, MYSQLI_ASSOC);
                                            }
                                            
                                            // Check if phone is duplicate
                                            $is_duplicate = isset($duplicate_phones[$lead['phoneno']]);
                                            $duplicate_count = $is_duplicate ? $duplicate_phones[$lead['phoneno']] : 0;
                                    ?>
                                    <tr id="lead-row-<?php echo $lead['id']; ?>">
                                        <td><?php echo $sno++; ?></td>
                                        <td><?php echo htmlspecialchars($lead['id']); ?></td>
                                        <td><?php echo htmlspecialchars($lead['name']); ?></td>
                                        <td><?php echo htmlspecialchars($lead['email']); ?></td>
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
                                                $currentTime = time();
                                                $lastCommentTime = strtotime($lead['last_comment_time']);
                                                $timeDiff = $currentTime - $lastCommentTime;
                                                
                                                $timeDisplay = '';
                                                $timeClass = '';

                                                if ($timeDiff < 60) {
                                                    $timeDisplay = 'Just now';
                                                } else if ($timeDiff < 3600) {
                                                    $minutes = floor($timeDiff / 60);
                                                    $timeDisplay = $minutes . ' min ago';
                                                    $timeClass = ($minutes > 30) ? 'time-urgent' : '';
                                                } elseif ($timeDiff < 86400) {
                                                    $hours = floor($timeDiff / 3600);
                                                    $timeDisplay = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
                                                    $timeClass = ($hours > 12) ? 'time-urgent' : '';
                                                } elseif ($timeDiff < 2592000) {
                                                    $days = floor($timeDiff / 86400);
                                                    $timeDisplay = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
                                                    $timeClass = ($days > 7) ? 'time-critical' : '';
                                                } else {
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
                                        <td>
                                            <?php if (!empty($lead['status'])): ?>
                                                <span class="badge badge-<?php echo strtolower(str_replace(' ', '_', $lead['status'])); ?> status-badge">
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
                                        <td><?php echo htmlspecialchars($lead['address']); ?></td>
                                        <td><?php echo htmlspecialchars($lead['city']); ?></td>
                                        <td><?php echo htmlspecialchars($lead['state']); ?></td>
                                        <td><?php echo htmlspecialchars($lead['pincode']); ?></td>
                                        <td><?php echo htmlspecialchars($lead['category']); ?></td>
                                        <td>
                                            <?php if (!empty($lead['followup_date'])): ?>
                                                <?php echo date('M j, Y g:i A', strtotime($lead['followup_date'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not Set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($lead['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="openCommentPopup(<?php echo $lead['id']; ?>, '<?php echo $lead['status']; ?>', '<?php echo $lead['sub_status'] ?? ''; ?>')">
                                                Add Comment (<?php echo count($comments); ?>)
                                            </button>
                                            
                                            <?php if (!empty($comments)): ?>
                                            <div class="comments-history-container mt-2">
                                                <?php foreach ($comments as $comment): ?>
                                                    <div class="comment-history-item">
                                                        <div class="comment-history-user">
                                                            <?php echo htmlspecialchars($comment['user_name'] ?: 'System'); ?>
                                                        </div>
                                                        <div class="comment-history-text">
                                                            <?php echo htmlspecialchars($comment['comments']); ?>
                                                        </div>
                                                        <div class="comment-history-time">
                                                            <?php echo date('M j, g:i A', strtotime($comment['datetime'])); ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php else: ?>
                                                <div class="no-comments">No comments yet</div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="14" class="text-center">No leads found.</td>
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
            
            <div class="sub-status-container" id="subStatusContainer">
                <label class="form-label">Sub Status <span class="sub-status-required" id="subStatusRequired" style="display: none;">*</span></label>
                <select name="sub_status" class="form-select" id="popupSubStatus">
                    <option value="">Select Sub Status</option>
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
        </div>
    </div>

    <!-- DataTables Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    
    <script>
        // Status to Sub Status Mapping from PHP
        const statusSubStatusMap = <?php echo json_encode($status_substatus_map); ?>;

        // Function to update time displays in real-time
        function updateTimeDisplays() {
            const timeElements = document.querySelectorAll('.time-since-comment');
            const now = Math.floor(Date.now() / 1000);
            
            timeElements.forEach(element => {
                const title = element.getAttribute('title');
                if (title && title.includes('Last comment:')) {
                    const timestampMatch = title.match(/Last comment: (.+)$/);
                    if (timestampMatch) {
                        const lastCommentTime = Math.floor(new Date(timestampMatch[1]).getTime() / 1000);
                        const timeDiff = now - lastCommentTime;
                        
                        let timeDisplay = '';
                        let timeClass = '';
                        
                        if (timeDiff < 3600) {
                            const minutes = Math.floor(timeDiff / 60);
                            timeDisplay = minutes + ' min ago';
                            timeClass = (minutes > 30) ? 'time-urgent' : '';
                        } else if (timeDiff < 86400) {
                            const hours = Math.floor(timeDiff / 3600);
                            timeDisplay = hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
                            timeClass = (hours > 12) ? 'time-urgent' : '';
                        } else if (timeDiff < 2592000) {
                            const days = Math.floor(timeDiff / 86400);
                            timeDisplay = days + ' day' + (days > 1 ? 's' : '') + ' ago';
                            timeClass = (days > 7) ? 'time-critical' : '';
                        } else {
                            const months = Math.floor(timeDiff / 2592000);
                            timeDisplay = months + ' month' + (months > 1 ? 's' : '') + ' ago';
                            timeClass = 'time-critical';
                        }
                        
                        element.innerHTML = '⏱️ ' + timeDisplay;
                        element.className = 'time-since-comment ' + timeClass;
                    }
                }
            });
        }

        // Initialize DataTable
        $(document).ready(function() {
            setInterval(updateTimeDisplays, 60000);

            $('#leadsTable').DataTable({
                "pageLength": 25,
                "order": [[0, 'asc']],
                "language": {
                    "search": "Search all columns:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "infoEmpty": "Showing 0 to 0 of 0 entries",
                    "infoFiltered": "(filtered from _MAX_ total entries)"
                },
                "drawCallback": function(settings) {
                    setTimeout(updateTimeDisplays, 100);
                }
            });
        });

        // Function to open WhatsApp - UPDATED VERSION
        function openWhatsApp(phoneNumber) {
            // Remove any non-digit characters from phone number
            let cleanPhone = phoneNumber.replace(/\D/g, '');
            
            // If phone number starts with 0, replace with 91
            if (cleanPhone.startsWith('0')) {
                cleanPhone = '91' + cleanPhone.substring(1);
            }
            
            // Create WhatsApp URL
            const whatsappUrl = `https://wa.me/${cleanPhone}`;
            
            // Open WhatsApp in new tab
            window.open(whatsappUrl, '_blank');
        }

        function openCommentPopup(leadId, currentStatus, currentSubStatus = '') {
            document.getElementById('popupLeadId').value = leadId;
            document.getElementById('popupStatus').value = currentStatus;
            
            if (currentSubStatus) {
                document.getElementById('popupSubStatus').value = currentSubStatus;
            }
            
            document.getElementById('followupDate').value = '';
            document.getElementById('overlay').style.display = 'block';
            document.getElementById('commentPopup').style.display = 'block';
            
            toggleSubStatus(currentStatus);
            loadComments(leadId);
        }
        
        function closeCommentPopup() {
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('commentPopup').style.display = 'none';
            document.getElementById('commentForm').reset();
            document.getElementById('subStatusContainer').style.display = 'none';
            document.getElementById('popupSubStatus').value = '';
            document.getElementById('subStatusRequired').style.display = 'none';
            document.getElementById('popupSubStatus').required = false;
        }
        
        function loadComments(leadId) {
            const commentsHistory = document.getElementById('commentsHistory');
            commentsHistory.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Loading comments...</div>';
            
            const xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    commentsHistory.innerHTML = xhr.responseText;
                }
            };
            
            xhr.open('GET', 'get_comments.php?lead_id=' + leadId, true);
            xhr.send();
        }
        
        function filterByStatus(status) {
            const url = new URL(window.location.href);
            url.searchParams.set('status', status);
            window.location.href = url.toString();
        }

        function toggleSubStatus(selectedStatus) {
            const subStatusContainer = document.getElementById('subStatusContainer');
            const subStatusSelect = document.getElementById('popupSubStatus');
            const subStatusRequired = document.getElementById('subStatusRequired');
            
            while (subStatusSelect.options.length > 1) {
                subStatusSelect.remove(1);
            }
            
            const statusData = statusSubStatusMap[selectedStatus];
            
            if (statusData && statusData.has_sub_status && statusData.sub_statuses.length > 0) {
                statusData.sub_statuses.forEach(subStatus => {
                    const option = document.createElement('option');
                    option.value = subStatus.name;
                    option.textContent = subStatus.name;
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
        
        // AJAX Comment Submission
        document.getElementById('commentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const leadId = formData.get('lead_id');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Updating...';
            submitBtn.disabled = true;
            
            fetch('ajax_update_comment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Status updated and comment added successfully!', 'success');
                    moveLeadToBottom(leadId);
                    setTimeout(() => {
                        closeCommentPopup();
                    }, 1000);
                } else {
                    showAlert(data.message || 'Error updating comment!', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Network error occurred!', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        function moveLeadToBottom(leadId) {
            const table = document.getElementById('leadsTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            const targetRow = rows.find(row => {
                const rowLeadId = row.cells[1].textContent.trim();
                return rowLeadId === leadId;
            });
            
            if (targetRow) {
                targetRow.classList.add('moving');
                targetRow.remove();
                tbody.appendChild(targetRow);
                updateSerialNumbers();
                setTimeout(() => {
                    targetRow.classList.remove('moving');
                }, 300);
            }
        }

        function updateSerialNumbers() {
            const rows = document.querySelectorAll('#leadsTable tbody tr');
            rows.forEach((row, index) => {
                row.cells[0].textContent = index + 1;
            });
        }

        function showAlert(message, type) {
            const existingAlerts = document.querySelectorAll('.custom-alert');
            existingAlerts.forEach(alert => alert.remove());
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} custom-alert`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('main');
            container.insertBefore(alertDiv, container.firstChild);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
        
        document.getElementById('overlay').addEventListener('click', closeCommentPopup);
    </script>
</body>
</html>