<?php
include_once __DIR__ .'/common/db_connection.php';
include_once __DIR__ .'/common/utils.php';

if (!isset($_SESSION['super_admin_user_id'])) {
    header('Location: login.php');
    exit;
}

// Initialize variables
$action = 'create';
$id = '';
$sub_status_name = '';
$status_id = '';
$sub_status_status = 1;
$statusMessage = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    
    if ($action === 'create') {
        // Add new sub status
        $sub_status_name = mysqli_real_escape_string($conn, $_POST['sub_status_name']);
        $status_id = mysqli_real_escape_string($conn, $_POST['status_id']);
        $sub_status_status = isset($_POST['sub_status_status']) ? 1 : 0;
        
        // Check if sub status already exists for this status
        $check_sql = "SELECT id FROM sub_status WHERE name = '$sub_status_name' AND status_id = '$status_id' AND is_deleted = 0";
        $check_result = mysqli_query($conn, $check_sql);
        
        if ($check_result && mysqli_num_rows($check_result) > 0) {
            $statusMessage = "Error: Sub Status with this name already exists for selected status!";
        } else {
            $sql = "INSERT INTO sub_status (name, status_id, status) VALUES ('$sub_status_name', '$status_id', '$sub_status_status')";
            if (mysqli_query($conn, $sql)) {
                $statusMessage = "Sub Status added successfully!";
                // Reset form
                $sub_status_name = '';
                $status_id = '';
                $sub_status_status = 1;
            } else {
                $statusMessage = "Error adding sub status: " . mysqli_error($conn);
            }
        }
    } 
    elseif ($action === 'update') {
        // Update sub status
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $sub_status_name = mysqli_real_escape_string($conn, $_POST['sub_status_name']);
        $status_id = mysqli_real_escape_string($conn, $_POST['status_id']);
        $sub_status_status = isset($_POST['sub_status_status']) ? $_POST['sub_status_status'] : 0;
        
        $sql = "UPDATE sub_status SET name='$sub_status_name', status_id='$status_id', status='$sub_status_status', updated_at=CURRENT_TIMESTAMP WHERE id='$id'";        
        if (mysqli_query($conn, $sql)) {
            $statusMessage = "Sub Status updated successfully!";
            // Reset to create mode
            $action = 'create';
            $sub_status_name = '';
            $status_id = '';
            $sub_status_status = 1;
            $id = '';
        } else {
            $statusMessage = "Error updating sub status: " . mysqli_error($conn);
        }
    }
    elseif ($action === 'edit') {
        // Load sub status data for editing
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $sql = "SELECT * FROM sub_status WHERE id='$id' AND is_deleted=0";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $action = 'update';
            $id = $row['id'];
            $sub_status_name = $row['name'];
            $status_id = $row['status_id'];
            $sub_status_status = $row['status'];
        }
    }
    elseif ($action === 'delete') {
        // Soft delete sub status
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $sql = "UPDATE sub_status SET is_deleted=1, deleted_at=CURRENT_TIMESTAMP WHERE id='$id'";
        if (mysqli_query($conn, $sql)) {
            $statusMessage = "Sub Status deleted successfully!";
        } else {
            $statusMessage = "Error deleting sub status: " . mysqli_error($conn);
        }
    }
}

// Handle create table request
if (isset($_GET['create_table']) && $_GET['create_table'] == 1) {
    // First check if status table exists
    $check_status_table = "SHOW TABLES LIKE 'status'";
    $status_table_result = mysqli_query($conn, $check_status_table);
    
    if (!$status_table_result || mysqli_num_rows($status_table_result) == 0) {
        $statusMessage = "Error: Status table does not exist. Please create status table first.";
    } else {
        // Drop table if exists to recreate with correct structure
        $drop_table_sql = "DROP TABLE IF EXISTS `sub_status`";
        mysqli_query($conn, $drop_table_sql);
        
        // Create sub_status table with correct structure including status_id
        $create_sub_status_table_sql = "CREATE TABLE IF NOT EXISTS `sub_status` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `status_id` INT(11) NOT NULL,
            `status` TINYINT(1) DEFAULT 1 COMMENT '1=active, 0=inactive',
            `is_deleted` TINYINT(1) DEFAULT 0 COMMENT '0=false, 1=true',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`status_id`) REFERENCES `status`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        if (mysqli_query($conn, $create_sub_status_table_sql)) {
            // Get status IDs for sample data
            $status_ids = [];
            $get_status_query = "SELECT id, name FROM status WHERE is_deleted = 0";
            $status_result = mysqli_query($conn, $get_status_query);
            if ($status_result && mysqli_num_rows($status_result) > 0) {
                while ($row = mysqli_fetch_assoc($status_result)) {
                    $status_ids[$row['name']] = $row['id'];
                }
                
                // Insert sample sub status data only if statuses exist
                if (!empty($status_ids)) {
                    $sample_values = [];
                    
                    if (isset($status_ids['Processing'])) {
                        $sample_values[] = "('Call Not Picked', {$status_ids['Processing']}, 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                        $sample_values[] = "('Call Cut', {$status_ids['Processing']}, 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                        $sample_values[] = "('Phone Off', {$status_ids['Processing']}, 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                        $sample_values[] = "('Number Busy', {$status_ids['Processing']}, 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                    }
                    
                    if (isset($status_ids['Follow Up'])) {
                        $sample_values[] = "('Follow Up Tomorrow', {$status_ids['Follow Up']}, 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                        $sample_values[] = "('Follow Up Next Week', {$status_ids['Follow Up']}, 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                    }
                    
                    if (isset($status_ids['Rejected'])) {
                        $sample_values[] = "('Not Interested', {$status_ids['Rejected']}, 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                        $sample_values[] = "('Wrong Number', {$status_ids['Rejected']}, 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                    }
                    
                    if (isset($status_ids['Duplicate'])) {
                        $sample_values[] = "('Duplicate Lead', {$status_ids['Duplicate']}, 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                    }
                    
                    if (!empty($sample_values)) {
                        $sample_sub_status_sql = "INSERT INTO `sub_status` (`name`, `status_id`, `status`, `is_deleted`, `created_at`, `updated_at`) VALUES " . implode(', ', $sample_values);
                        mysqli_query($conn, $sample_sub_status_sql);
                    }
                }
            }
            
            $statusMessage = "Sub Status table created successfully with status_id column!" . (!empty($sample_values) ? " Sample data added." : " No sample data added - no active statuses found.");
        } else {
            $statusMessage = "Error creating table: " . mysqli_error($conn);
        }
    }
}

// Handle restore sub status (GET request)
if (isset($_GET['restore_sub_status'])) {
    $id = mysqli_real_escape_string($conn, $_GET['restore_sub_status']);
    $sql = "UPDATE sub_status SET is_deleted=0, deleted_at=NULL WHERE id='$id'";
    if (mysqli_query($conn, $sql)) {
        $statusMessage = "Sub Status restored successfully!";
    } else {
        $statusMessage = "Error restoring sub status: " . mysqli_error($conn);
    }
}

// Get all active statuses for dropdown
$active_statuses_query = "SELECT * FROM status WHERE is_deleted = 0 AND status = 1 ORDER BY name";
$active_statuses_result = mysqli_query($conn, $active_statuses_query);
$active_statuses = [];
if ($active_statuses_result && mysqli_num_rows($active_statuses_result) > 0) {
    $active_statuses = mysqli_fetch_all($active_statuses_result, MYSQLI_ASSOC);
}

// Get all active sub statuses with status names
$active_sub_statuses_query = "SELECT ss.*, s.name as status_name 
                             FROM sub_status ss 
                             LEFT JOIN status s ON ss.status_id = s.id 
                             WHERE ss.is_deleted = 0 
                             ORDER BY s.name, ss.name";
$active_sub_statuses_result = mysqli_query($conn, $active_sub_statuses_query);
$active_sub_statuses = [];
if ($active_sub_statuses_result && mysqli_num_rows($active_sub_statuses_result) > 0) {
    $active_sub_statuses = mysqli_fetch_all($active_sub_statuses_result, MYSQLI_ASSOC);
}

// Get deleted sub statuses with status names
$deleted_sub_statuses_query = "SELECT ss.*, s.name as status_name 
                              FROM sub_status ss 
                              LEFT JOIN status s ON ss.status_id = s.id 
                              WHERE ss.is_deleted = 1 
                              ORDER BY ss.deleted_at DESC";
$deleted_sub_statuses_result = mysqli_query($conn, $deleted_sub_statuses_query);
$deleted_sub_statuses = [];
if ($deleted_sub_statuses_result && mysqli_num_rows($deleted_sub_statuses_result) > 0) {
    $deleted_sub_statuses = mysqli_fetch_all($deleted_sub_statuses_result, MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sub Status Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .status-deleted {
            background: #fff3cd;
            color: #856404;
        }
        .table-actions {
            white-space: nowrap;
        }
        .card {
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 0.375rem;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.125);
        }
        .badge-new { background-color: #0d6efd; }
        .badge-processing { background-color: #6f42c1; }
        .badge-follow_up { background-color: #ffc107; color: #000; }
        .badge-confirmed { background-color: #198754; }
        .badge-converted { background-color: #20c997; }
        .badge-rejected { background-color: #dc3545; }
        .badge-duplicate { background-color: #fd7e14; }
        .badge-trash { background-color: #6c757d; }
        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }
    </style>
</head>
<body>
    <?php include ('common/header.php'); ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include ('common/sidebar.php'); ?>
            
            <main class="col-md-9 ml-sm-auto col-lg-10 px-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Sub Status Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="status.php" class="btn btn-outline-primary">
                            <i class="fas fa-tags me-1"></i> Manage Status
                        </a>
                    </div>
                </div>

                <?php if ($statusMessage): ?>
                    <div class="alert alert-<?php echo strpos($statusMessage, 'Error') !== false ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                        <?php echo $statusMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Check if status table exists and has data -->
                <?php 
                $check_status = "SHOW TABLES LIKE 'status'";
                $status_table_exists = mysqli_query($conn, $check_status) && mysqli_num_rows(mysqli_query($conn, $check_status)) > 0;
                
                $check_status_data = "SELECT COUNT(*) as count FROM status WHERE is_deleted = 0 AND status = 1";
                $status_data_result = mysqli_query($conn, $check_status_data);
                $has_active_statuses = $status_data_result && mysqli_fetch_assoc($status_data_result)['count'] > 0;
                ?>

                <?php if (!$status_table_exists): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Status table not found!</strong> Please create the status table first.
                        <a href="status.php?create_table=1" class="btn btn-sm btn-warning ml-2">
                            <i class="fas fa-table me-1"></i> Create Status Table
                        </a>
                    </div>
                <?php elseif (!$has_active_statuses): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>No active statuses found!</strong> Please add some statuses first.
                        <a href="status.php" class="btn btn-sm btn-warning ml-2">
                            <i class="fas fa-plus me-1"></i> Add Status
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Check if sub_status table exists and has status_id column -->
                <?php 
                $check_sub_status_table = "SHOW TABLES LIKE 'sub_status'";
                $sub_status_table_exists = mysqli_query($conn, $check_sub_status_table) && mysqli_num_rows(mysqli_query($conn, $check_sub_status_table)) > 0;
                
                $check_status_id_column = false;
                if ($sub_status_table_exists) {
                    $check_column_sql = "SHOW COLUMNS FROM sub_status LIKE 'status_id'";
                    $column_result = mysqli_query($conn, $check_column_sql);
                    $check_status_id_column = $column_result && mysqli_num_rows($column_result) > 0;
                }
                ?>

                <?php if (!$sub_status_table_exists || !$check_status_id_column): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Sub Status table not properly configured!</strong> The status_id column is missing.
                        <a href="sub_status.php?create_table=1" class="btn btn-sm btn-danger ml-2">
                            <i class="fas fa-table me-1"></i> Recreate Sub Status Table
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Add/Edit Sub Status Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <?php if ($action === 'update'): ?>
                                <i class="fas fa-edit"></i> Edit Sub Status
                            <?php else: ?>
                                <i class="fas fa-plus"></i> Add New Sub Status
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $action; ?>">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">

                            <div class="form-group">
                                <label for="status_id">Select Status</label>
                                <select class="form-control" id="status_id" name="status_id" required <?php echo ($action === 'update') ? 'disabled' : ''; echo (!$has_active_statuses || !$check_status_id_column) ? ' disabled' : ''; ?>>
                                    <option value="">Select Status</option>
                                    <?php if (count($active_statuses) > 0): ?>
                                        <?php foreach ($active_statuses as $status): ?>
                                            <option value="<?php echo $status['id']; ?>" <?php echo ($status_id == $status['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No active statuses found</option>
                                    <?php endif; ?>
                                </select>
                                <?php if ($action === 'update'): ?>
                                    <input type="hidden" name="status_id" value="<?php echo $status_id; ?>">
                                    <small class="form-text text-muted">Status cannot be changed when editing sub status</small>
                                <?php else: ?>
                                    <small class="form-text text-muted">Select the parent status for this sub status</small>
                                <?php endif; ?>
                                <?php if (!$has_active_statuses): ?>
                                    <small class="form-text text-danger">Please add active statuses first to create sub statuses</small>
                                <?php elseif (!$check_status_id_column): ?>
                                    <small class="form-text text-danger">Sub Status table needs to be recreated</small>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="sub_status_name">Sub Status Name</label>
                                <input type="text" class="form-control" id="sub_status_name" name="sub_status_name" 
                                       value="<?php echo htmlspecialchars($sub_status_name); ?>" 
                                       placeholder="Enter sub status name" required <?php echo (!$has_active_statuses || !$check_status_id_column) ? ' disabled' : ''; ?>>
                                <small class="form-text text-muted">This sub status will be linked to the selected status</small>
                            </div>

                            <div class="form-group">
                                <label for="sub_status_status">Sub Status</label>
                                <select class="form-control" name="sub_status_status" required <?php echo (!$has_active_statuses || !$check_status_id_column) ? ' disabled' : ''; ?>>
                                    <option value="1" <?php echo ($sub_status_status == 1) ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?php echo ($sub_status_status == 0) ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <small class="form-text text-muted">Inactive sub statuses won't appear in dropdowns</small>
                            </div>

                            <button type="submit" class="btn btn-primary" <?php echo (!$has_active_statuses || !$check_status_id_column) ? ' disabled' : ''; ?>>
                                <?php if ($action === 'update'): ?>
                                    <i class="fas fa-sync-alt"></i> Update Sub Status
                                <?php else: ?>
                                    <i class="fas fa-plus"></i> Add Sub Status
                                <?php endif; ?>
                            </button>
                            
                            <?php if ($action === 'update'): ?>
                                <a href="sub_status.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <?php if ($sub_status_table_exists && $check_status_id_column): ?>
                    <!-- Active Sub Statuses List -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Active Sub Statuses</h5>
                            <span class="badge badge-primary"><?php echo count($active_sub_statuses); ?> sub statuses</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th width="5%">ID</th>
                                            <th width="25%">Sub Status Name</th>
                                            <th width="20%">Parent Status</th>
                                            <th width="15%">Status</th>
                                            <th width="20%">Created At</th>
                                            <th width="15%">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($active_sub_statuses)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                                        <br>
                                                        No sub statuses found. Add your first sub status!
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1; ?>
                                            <?php foreach ($active_sub_statuses as $sub_status): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($sub_status['name']); ?></td>
                                                <td>
                                                    <?php if (!empty($sub_status['status_name'])): ?>
                                                        <span class="badge badge-<?php echo strtolower(str_replace(' ', '_', $sub_status['status_name'])); ?> status-badge">
                                                            <?php echo htmlspecialchars($sub_status['status_name']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Status Not Found</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?php echo $sub_status['status'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $sub_status['status'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y g:i A', strtotime($sub_status['created_at'])); ?></td>
                                                <td class="table-actions">
                                                    <!-- Edit Form -->
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="edit">
                                                        <input type="hidden" name="id" value="<?php echo $sub_status['id']; ?>">
                                                        <button type="submit" class="btn btn-warning btn-sm">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                    </form>
                                                    
                                                    <!-- Delete Form -->
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $sub_status['id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($sub_status['name']); ?>?')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Deleted Sub Statuses List -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Deleted Sub Statuses</h5>
                            <span class="badge badge-warning"><?php echo count($deleted_sub_statuses); ?> deleted</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th width="5%">ID</th>
                                            <th width="25%">Sub Status Name</th>
                                            <th width="20%">Parent Status</th>
                                            <th width="15%">Status</th>
                                            <th width="25%">Deleted At</th>
                                            <th width="15%">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($deleted_sub_statuses)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="fas fa-trash-restore fa-3x mb-3"></i>
                                                        <br>
                                                        No deleted sub statuses found.
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1; ?>
                                            <?php foreach ($deleted_sub_statuses as $sub_status): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($sub_status['name']); ?></td>
                                                <td><?php echo !empty($sub_status['status_name']) ? htmlspecialchars($sub_status['status_name']) : 'Status Not Found'; ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $sub_status['status'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $sub_status['status'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y g:i A', strtotime($sub_status['deleted_at'])); ?></td>
                                                <td class="table-actions">
                                                    <a href="sub_status.php?restore_sub_status=<?php echo $sub_status['id']; ?>" 
                                                       class="btn btn-success btn-sm"
                                                       onclick="return confirm('Restore <?php echo htmlspecialchars($sub_status['name']); ?>?')">
                                                        <i class="fas fa-trash-restore"></i> Restore
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php include ('common/footer.php'); ?>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);

            // Focus on sub status name field when page loads (if not in edit mode)
            <?php if ($action === 'create' && $has_active_statuses && $check_status_id_column): ?>
                $('#sub_status_name').focus();
            <?php endif; ?>

            // Add confirmation for delete actions
            $('form').on('submit', function(e) {
                if ($(this).find('input[name="action"]').val() === 'delete') {
                    if (!confirm('Are you sure you want to delete this sub status?')) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
</body>
</html>