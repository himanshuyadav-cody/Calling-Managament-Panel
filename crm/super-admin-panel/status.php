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
$status_name = '';
$status = 1;
$statusMessage = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    
    if ($action === 'create') {
        // Add new status
        $status_name = mysqli_real_escape_string($conn, $_POST['status_name']);
        $status = isset($_POST['status']) ? 1 : 0;
        
        // Check if status already exists
        $check_sql = "SELECT id FROM status WHERE name = '$status_name' AND is_deleted = 0";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $statusMessage = "Error: Status with this name already exists!";
        } else {
            $sql = "INSERT INTO status (name, status) VALUES ('$status_name', '$status')";
            if (mysqli_query($conn, $sql)) {
                $statusMessage = "Status added successfully!";
                // Reset form
                $status_name = '';
                $status = 1;
            } else {
                // If table doesn't exist, show create table option
                if (mysqli_errno($conn) == 1146) { // Table doesn't exist
                    $statusMessage = "Error: Status table doesn't exist. <a href='status.php?create_table=1' class='btn btn-sm btn-warning'>Create Table Now</a>";
                } else {
                    $statusMessage = "Error adding status: " . mysqli_error($conn);
                }
            }
        }
    } 
    elseif ($action === 'update') {
        // Update status
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $status_name = mysqli_real_escape_string($conn, $_POST['status_name']);
        $status = isset($_POST['status']) ? $_POST['status'] : 0;
        
        // Check if status already exists (excluding current one)
        $check_sql = "SELECT id FROM status WHERE name = '$status_name' AND id != '$id' AND is_deleted = 0";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $statusMessage = "Error: Status with this name already exists!";
        } else {
            $sql = "UPDATE status SET name='$status_name', status='$status', updated_at=CURRENT_TIMESTAMP WHERE id='$id'";        
            if (mysqli_query($conn, $sql)) {
                $statusMessage = "Status updated successfully!";
                // Reset to create mode
                $action = 'create';
                $status_name = '';
                $status = 1;
                $id = '';
            } else {
                $statusMessage = "Error updating status: " . mysqli_error($conn);
            }
        }
    }
    elseif ($action === 'edit') {
        // Load status data for editing
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $sql = "SELECT * FROM status WHERE id='$id' AND is_deleted=0";
        $result = mysqli_query($conn, $sql);
        if ($row = mysqli_fetch_assoc($result)) {
            $action = 'update';
            $id = $row['id'];
            $status_name = $row['name'];
            $status = $row['status'];
        }
    }
    elseif ($action === 'delete') {
        // Soft delete status
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $sql = "UPDATE status SET is_deleted=1, deleted_at=CURRENT_TIMESTAMP WHERE id='$id'";
        if (mysqli_query($conn, $sql)) {
            $statusMessage = "Status deleted successfully!";
        } else {
            $statusMessage = "Error deleting status: " . mysqli_error($conn);
        }
    }
}

// Handle create table request
if (isset($_GET['create_table']) && $_GET['create_table'] == 1) {
    $create_table_sql = "CREATE TABLE IF NOT EXISTS `status` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `status` TINYINT(1) DEFAULT 1 COMMENT '1=active, 0=inactive',
        `is_deleted` TINYINT(1) DEFAULT 0 COMMENT '0=false, 1=true',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (mysqli_query($conn, $create_table_sql)) {
        // Insert sample data
        $sample_data_sql = "INSERT INTO `status` (`name`, `status`, `is_deleted`, `created_at`, `updated_at`) VALUES
            ('New', 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
            ('Processing', 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
            ('Follow Up', 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
            ('Confirmed', 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
            ('Converted', 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
            ('Rejected', 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
            ('Duplicate', 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
            ('Trash', 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        mysqli_query($conn, $sample_data_sql);
        
        $statusMessage = "Status table created successfully with sample data!";
    } else {
        $statusMessage = "Error creating table: " . mysqli_error($conn);
    }
}

// Handle restore status (GET request)
if (isset($_GET['restore_status'])) {
    $id = mysqli_real_escape_string($conn, $_GET['restore_status']);
    $sql = "UPDATE status SET is_deleted=0, deleted_at=NULL WHERE id='$id'";
    if (mysqli_query($conn, $sql)) {
        $statusMessage = "Status restored successfully!";
    } else {
        $statusMessage = "Error restoring status: " . mysqli_error($conn);
    }
}

// Get all active statuses
$active_statuses_query = "SELECT s.*, 
                         (SELECT COUNT(*) FROM sub_status WHERE status_id = s.id AND is_deleted = 0) as sub_status_count
                         FROM status s 
                         WHERE s.is_deleted = 0 
                         ORDER BY s.name";
$active_statuses_result = mysqli_query($conn, $active_statuses_query);
$active_statuses = [];
if ($active_statuses_result) {
    $active_statuses = mysqli_fetch_all($active_statuses_result, MYSQLI_ASSOC);
} else {
    $active_statuses = [];
}

// Get deleted statuses
$deleted_statuses_query = "SELECT * FROM status WHERE is_deleted = 0 ORDER BY deleted_at DESC";
$deleted_statuses_result = mysqli_query($conn, $deleted_statuses_query);
$deleted_statuses = [];
if ($deleted_statuses_result) {
    $deleted_statuses = mysqli_fetch_all($deleted_statuses_result, MYSQLI_ASSOC);
} else {
    $deleted_statuses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Management</title>
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
        .sub-status-indicator {
            font-size: 10px;
            background: #e9ecef;
            color: #6c757d;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
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
                    <h1 class="h2">Status Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="sub_status.php" class="btn btn-outline-primary">
                            <i class="fas fa-list-alt me-1"></i> Manage Sub Status
                        </a>
                    </div>
                </div>

                <?php if ($statusMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $statusMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Add/Edit Status Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <?php if ($action === 'update'): ?>
                                <i class="fas fa-edit"></i> Edit Status
                            <?php else: ?>
                                <i class="fas fa-plus"></i> Add New Status
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $action; ?>">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">

                            <div class="form-group">
                                <label for="status_name">Status Name</label>
                                <input type="text" class="form-control" id="status_name" name="status_name" 
                                       value="<?php echo htmlspecialchars($status_name); ?>" 
                                       placeholder="Enter status name" required>
                                <small class="form-text text-muted">This status will be used in leads management</small>
                            </div>

                            <div class="form-group">
                                <label for="status">Status</label>
                                <select class="form-control" name="status" required>
                                    <option value="1" <?php echo ($status == 1) ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?php echo ($status == 0) ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <small class="form-text text-muted">Inactive statuses won't appear in dropdowns</small>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <?php if ($action === 'update'): ?>
                                    <i class="fas fa-sync-alt"></i> Update Status
                                <?php else: ?>
                                    <i class="fas fa-plus"></i> Add Status
                                <?php endif; ?>
                            </button>
                            
                            <?php if ($action === 'update'): ?>
                                <a href="status.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Active Statuses List -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Active Statuses</h5>
                        <span class="badge badge-primary"><?php echo count($active_statuses); ?> statuses</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th width="5%">ID</th>
                                        <th width="25%">Status Name</th>
                                        <th width="15%">Sub Statuses</th>
                                        <th width="15%">Status</th>
                                        <th width="20%">Created At</th>
                                        <th width="20%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($active_statuses)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                                    <br>
                                                    No statuses found. Add your first status!
                                                    <?php if (mysqli_errno($conn) == 1146): ?>
                                                        <br>
                                                        <a href="status.php?create_table=1" class="btn btn-warning mt-2">
                                                            <i class="fas fa-table me-1"></i> Create Status Table
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $no = 1; ?>
                                        <?php foreach ($active_statuses as $status): ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo strtolower(str_replace(' ', '_', $status['name'])); ?> status-badge">
                                                    <?php echo htmlspecialchars($status['name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($status['sub_status_count'] > 0): ?>
                                                    <span class="badge badge-info">
                                                        <?php echo $status['sub_status_count']; ?> sub statuses
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">No sub status</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $status['status'] ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo $status['status'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($status['created_at'])); ?></td>
                                            <td class="table-actions">
                                                <!-- Edit Form -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="id" value="<?php echo $status['id']; ?>">
                                                    <button type="submit" class="btn btn-warning btn-sm">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                </form>
                                                
                                                <!-- Delete Form -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $status['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($status['name']); ?>?')">
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

                <!-- Deleted Statuses List -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Deleted Statuses</h5>
                        <span class="badge badge-warning"><?php echo count($deleted_statuses); ?> deleted</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th width="5%">ID</th>
                                        <th width="25%">Status Name</th>
                                        <th width="15%">Status</th>
                                        <th width="25%">Deleted At</th>
                                        <th width="15%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($deleted_statuses)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="fas fa-trash-restore fa-3x mb-3"></i>
                                                    <br>
                                                    No deleted statuses found.
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $no = 1; ?>
                                        <?php foreach ($deleted_statuses as $status): ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($status['name']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $status['status'] ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo $status['status'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($status['deleted_at'])); ?></td>
                                            <td class="table-actions">
                                                <a href="status.php?restore_status=<?php echo $status['id']; ?>" 
                                                   class="btn btn-success btn-sm"
                                                   onclick="return confirm('Restore <?php echo htmlspecialchars($status['name']); ?>?')">
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

            // Focus on status name field when page loads (if not in edit mode)
            <?php if ($action === 'create'): ?>
                $('#status_name').focus();
            <?php endif; ?>

            // Add confirmation for delete actions
            $('form[action="delete"]').on('submit', function(e) {
                if (!confirm('Are you sure you want to delete this status?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>