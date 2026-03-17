<?php
include_once __DIR__ .'/common/db_connection.php';
include_once __DIR__ .'/common/utils.php';

if (!isset($_SESSION['super_admin_user_id'])) {
    header('Location: login.php');
    exit;
}

$action = 'create';
$id = '';
$name = '';
$email = '';
$phone = '';
$password = '';
$status = '';
$category_id = '';
$statusMessage = '';

function handleUserOperations($conn) {
    global $action, $id, $name, $email, $phone, $password, $status, $category_id, $statusMessage;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user_id = $_SESSION['super_admin_user_id'];
        foreach ($_POST as $key => $value) {
            $_POST[$key] = is_array($value) ? array_map('trim', $value) : trim($value);
        }

        $action = $_POST['action'];
        $id = $_POST['id'] ?? null;
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $password = $_POST['password'] ?? '';
        $status = $_POST['status'] ?? '';
        $category_id = $_POST['category_id'] ?? '';

        // Only check for duplicates if we're creating or updating with new values
        if ($action === 'create' || $action === 'update') {
            // Check for duplicate email
            $emailCheckSql = "SELECT id FROM users WHERE email = ? AND is_deleted = 0";        
            if ($action === 'update' && $id) {
                $emailCheckSql .= " AND id != ?";
            }
            $emailStmt = $conn->prepare($emailCheckSql);
            if ($action === 'update' && $id) {
                $emailStmt->bind_param("si", $email, $id);
            } else {
                $emailStmt->bind_param("s", $email);
            }
            $emailStmt->execute();
            $emailResult = $emailStmt->get_result();
            
            if ($emailResult->num_rows > 0) {
                $statusMessage = "Error: Email already exists!";
                $emailStmt->close();
                return;
            }
            $emailStmt->close();

            // Check for duplicate phone
            $phoneCheckSql = "SELECT id FROM users WHERE phone = ? AND is_deleted = 0";
            if ($action === 'update' && $id) {
                $phoneCheckSql .= " AND id != ?";
            }
            $phoneStmt = $conn->prepare($phoneCheckSql);
            if ($action === 'update' && $id) {
                $phoneStmt->bind_param("si", $phone, $id);
            } else {
                $phoneStmt->bind_param("s", $phone);
            }
            $phoneStmt->execute();
            $phoneResult = $phoneStmt->get_result();
            
            if ($phoneResult->num_rows > 0) {
                $statusMessage = "Error: Phone number already exists!";
                $phoneStmt->close();
                return;
            }
            $phoneStmt->close();
        }

        if ($action === 'create') {
            $hashedPassword = encryptPassword($password);          
            $sql = "INSERT INTO users (name, email, phone, password, status, category, role) VALUES (?, ?, ?, ?, ?, ?, '1')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssii", $name, $email, $phone, $hashedPassword, $status, $category_id);

            if ($stmt->execute()) {
                $statusMessage = "User Created Successfully.";
                header("Refresh: 2; URL=users.php");
            } else {
                $statusMessage = "Error creating user: " . $stmt->error;
            }
            $stmt->close();

        } elseif ($action === 'edit' && $id) {
            $sql = "SELECT * FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            if ($user) {
                $name = $user['name'];
                $email = $user['email'];
                $phone = $user['phone'];
                $password = decryptPassword($user['password']); 
                $status = $user['status'];
                $category_id = $user['category'];
                $action = 'update';
            }
            $stmt->close();

        } elseif ($action === 'update' && $id) {
            $password_value = $password ? encryptPassword($password) : $password;

            // If password is empty, don't update it
            if (empty($password)) {
                $sql = "UPDATE users SET name = ?, email = ?, phone = ?, status = ?, category = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssiii", $name, $email, $phone, $status, $category_id, $id);
            } else {
                $sql = "UPDATE users SET name = ?, email = ?, phone = ?, password = ?, status = ?, category = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssiii", $name, $email, $phone, $password_value, $status, $category_id, $id);
            }

            if ($stmt->execute()) {
                $statusMessage = "User Updated Successfully.";
                header("Refresh: 2; URL=users.php");
            } else {
                $statusMessage = "Error updating user: " . $stmt->error;
            }
            $stmt->close();

        } elseif ($action === 'delete' && $id) {
            // Fetch the current email and phone of the user
            $sql = "SELECT name, email, phone FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
        
            if ($user) {
                $timestamp = time();
                $new_name = "disable_{$timestamp}_{$user['name']}";
                $new_email = "disable_{$timestamp}_{$user['email']}";
                $new_phone = "disable_{$timestamp}_{$user['phone']}";
        
                // Update the name, email and phone before marking as deleted
                $sql = "UPDATE users SET name = ?, email = ?, phone = ?, is_deleted = 1 WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $new_name, $new_email, $new_phone, $id);
                $stmt->execute();
                $stmt->close();
            }
        
            $statusMessage = "User Deleted Successfully.";
            header("Refresh: 2; URL=users.php");
        }
    }
}

function fetchUsers($conn) {
    $sql = "SELECT u.*, c.name as category_name 
            FROM users u 
            LEFT JOIN categories c ON u.category = c.id 
            WHERE u.role = '1' AND u.is_deleted = 0 
            ORDER BY u.id DESC";
    
    $result = $conn->query($sql);
    
    // Check if query was successful
    if ($result === false) {
        echo "Error in SQL query: " . $conn->error;
        return [];
    }
    
    // Check if we have results
    if ($result->num_rows > 0) {
        return $result->fetch_all(MYSQLI_ASSOC);
    } else {
        return [];
    }
}

function fetchCategories($conn) {
    $sql = "SELECT id, name FROM categories where is_deleted = 0 ORDER BY name";
    $result = $conn->query($sql);
    
    // Check if query was successful
    if ($result === false) {
        echo "Error in SQL query: " . $conn->error;
        return [];
    }
    
    // Check if we have results
    if ($result->num_rows > 0) {
        return $result->fetch_all(MYSQLI_ASSOC);
    } else {
        return [];
    }
}

handleUserOperations($conn);
$users = fetchUsers($conn);
$categories = fetchCategories($conn);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <?php include ('common/header.php'); ?>
    <div class="container-fluid">
        <div class="row">
            <?php include ('common/sidebar.php'); ?>
            <div class="container mt-4">
                <h1 class="mb-4">Store Management</h1>

                <?php if ($statusMessage): ?>
                    <div class="alert alert-<?php echo strpos($statusMessage, 'Error') === false ? 'success' : 'danger'; ?>">
                        <?php echo $statusMessage; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="mb-4">
                    <input type="hidden" name="action" value="<?php echo $action; ?>">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">

                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="text" class="form-control" name="password" value="<?php echo htmlspecialchars($password); ?>" <?php echo $action === 'create' ? 'required' : 'placeholder="Leave blank to keep current password"'; ?>>
                    </div>
                    
                    <!-- Category Dropdown -->
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select class="form-control" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                    <?php echo ($category_id == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" name="status" required>
                            <option value="1" <?php echo ($status == 1) ? 'selected' : ''; ?>>Active</option>
                            <option value="2" <?php echo ($status == 2) ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>                

                    <button type="submit" class="btn btn-primary">
                        <?php echo $action === 'update' ? 'Update User' : 'Create User'; ?>
                    </button>
                    
                    <?php if ($action === 'update'): ?>
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </form>

                <h2>List of Users</h2>
                <table class="table table-bordered">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (count($users) > 0) {
                            $no = 1;
                            foreach ($users as $user) { ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo $user['email']; ?></td>
                                    <td><?php echo $user['phone']; ?></td>
                                    <td><?php echo $user['category_name'] ?? 'N/A'; ?></td>
                                    <td><?php echo ($user['status'] == 1) ? 'Active' : 'Inactive'; ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-warning btn-sm">Edit</button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php }
                        } else { ?>
                            <tr>
                                <td colspan="7" class="text-center">No users found</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include ('common/footer.php'); ?>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/js/bootstrap.min.js"></script>
</body>
</html>