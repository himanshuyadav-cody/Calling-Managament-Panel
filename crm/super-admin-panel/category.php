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
$category_name = '';
$status = 1;
$statusMessage = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    
    if ($action === 'create') {
        // Add new category
        $category_name = mysqli_real_escape_string($conn, $_POST['category_name']);
        $status = isset($_POST['status']) ? 1 : 0;
        
        $sql = "INSERT INTO categories (name, status) VALUES ('$category_name', '$status')";
        if (mysqli_query($conn, $sql)) {
            $statusMessage = "Category added successfully!";
            // Reset form
            $category_name = '';
            $status = 1;
        } else {
            $statusMessage = "Error adding category: " . mysqli_error($conn);
        }
    } 
    elseif ($action === 'update') {
        // Update category
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $category_name = mysqli_real_escape_string($conn, $_POST['category_name']);
        $status = isset($_POST['status']) ? $_POST['status'] : 0;
        
        $sql = "UPDATE categories SET name='$category_name', status='$status', updated_at=CURRENT_TIMESTAMP WHERE id='$id'";        
        if (mysqli_query($conn, $sql)) {
            $statusMessage = "Category updated successfully!";
            // Reset to create mode
            $action = 'create';
            $category_name = '';
            $status = 1;
            $id = '';
        } else {
            $statusMessage = "Error updating category: " . mysqli_error($conn);
        }
    }
    elseif ($action === 'edit') {
        // Load category data for editing
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $sql = "SELECT * FROM categories WHERE id='$id' AND is_deleted=0";
        $result = mysqli_query($conn, $sql);
        if ($row = mysqli_fetch_assoc($result)) {
            $action = 'update';
            $id = $row['id'];
            $category_name = $row['name'];
            $status = $row['status'];
        }
    }
    elseif ($action === 'delete') {
        // Soft delete category
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $sql = "UPDATE categories SET is_deleted=1, deleted_at=CURRENT_TIMESTAMP WHERE id='$id'";
        if (mysqli_query($conn, $sql)) {
            $statusMessage = "Category deleted successfully!";
        } else {
            $statusMessage = "Error deleting category: " . mysqli_error($conn);
        }
    }
}

// Handle restore category (GET request)
if (isset($_GET['restore_category'])) {
    $id = mysqli_real_escape_string($conn, $_GET['restore_category']);
    $sql = "UPDATE categories SET is_deleted=0, deleted_at=NULL WHERE id='$id'";
    if (mysqli_query($conn, $sql)) {
        $statusMessage = "Category restored successfully!";
    } else {
        $statusMessage = "Error restoring category: " . mysqli_error($conn);
    }
}

// Get all active categories
$active_categories_query = "SELECT * FROM categories WHERE is_deleted = 0 ORDER BY name";
$active_categories_result = mysqli_query($conn, $active_categories_query);
$active_categories = [];
if ($active_categories_result) {
    $active_categories = mysqli_fetch_all($active_categories_result, MYSQLI_ASSOC);
}

// Get deleted categories
$deleted_categories_query = "SELECT * FROM categories WHERE is_deleted = 1 ORDER BY deleted_at DESC";
$deleted_categories_result = mysqli_query($conn, $deleted_categories_query);
$deleted_categories = [];
if ($deleted_categories_result) {
    $deleted_categories = mysqli_fetch_all($deleted_categories_result, MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management</title>
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
    </style>
</head>
<body>
    <?php include ('common/header.php'); ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include ('common/sidebar.php'); ?>
            
            <main class="col-md-9 ml-sm-auto col-lg-10 px-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Category Management</h1>
                </div>

                <?php if ($statusMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $statusMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Add/Edit Category Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <?php if ($action === 'update'): ?>
                                <i class="fas fa-edit"></i> Edit Category
                            <?php else: ?>
                                <i class="fas fa-plus"></i> Add New Category
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $action; ?>">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">

                            <div class="form-group">
                                <label for="category_name">Category Name</label>
                                <input type="text" class="form-control" id="category_name" name="category_name" 
                                       value="<?php echo htmlspecialchars($category_name); ?>" 
                                       placeholder="Enter category name" required>
                            </div>

                            <div class="form-group">
                                <label for="status">Status</label>
                                <select class="form-control" name="status" required>
                                    <option value="1" <?php echo ($status == 1) ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?php echo ($status == 0) ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <?php if ($action === 'update'): ?>
                                    <i class="fas fa-sync-alt"></i> Update Category
                                <?php else: ?>
                                    <i class="fas fa-plus"></i> Add Category
                                <?php endif; ?>
                            </button>
                            
                            <?php if ($action === 'update'): ?>
                                <a href="category.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Active Categories List -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Active Categories</h5>
                        <span class="badge badge-primary"><?php echo count($active_categories); ?> categories</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Category Name</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th>Updated At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($active_categories)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                                    <br>
                                                    No categories found. Add your first category!
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $no = 1; ?>
                                        <?php foreach ($active_categories as $category): ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $category['status'] ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo $category['status'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($category['created_at'])); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($category['updated_at'])); ?></td>
                                            <td class="table-actions">
                                                <!-- Edit Form -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" class="btn btn-warning btn-sm">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                </form>
                                                
                                                <!-- Delete Form -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($category['name']); ?>?')">
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

                <!-- Deleted Categories List -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Deleted Categories</h5>
                        <span class="badge badge-warning"><?php echo count($deleted_categories); ?> deleted</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Category Name</th>
                                        <th>Status</th>
                                        <th>Deleted At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($deleted_categories)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="fas fa-trash-restore fa-3x mb-3"></i>
                                                    <br>
                                                    No deleted categories found.
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $no = 1; ?>
                                        <?php foreach ($deleted_categories as $category): ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                                            <td>
                                                <span class="status-badge status-deleted">
                                                    <i class="fas fa-trash"></i> Deleted
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($category['deleted_at'])); ?></td>
                                            <td class="table-actions">
                                                <a href="category.php?restore_category=<?php echo $category['id']; ?>" 
                                                   class="btn btn-success btn-sm"
                                                   onclick="return confirm('Restore <?php echo htmlspecialchars($category['name']); ?>?')">
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

            // Focus on category name field when page loads (if not in edit mode)
            <?php if ($action === 'create'): ?>
                $('#category_name').focus();
            <?php endif; ?>
        });
    </script>
</body>
</html>