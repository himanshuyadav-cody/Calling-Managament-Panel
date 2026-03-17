<?php
include_once __DIR__ .'/common/db_connection.php';
if (!isset($_SESSION['super_admin_user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle actions before any HTML output
if (isset($_POST['download_sample'])) {
    downloadSampleCSV();
}

// Handle Assign Leads
if (isset($_POST['assign_leads'])) {
    assignLeadsToUsers($conn);
}

// Start output buffering
ob_start();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bulk Leads Upload</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; margin: 10px 0; }
        .error { color: red; margin: 10px 0; }
        .btn { padding: 10px 15px; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #007bff; }
        .btn-primary:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include('common/header.php'); ?>
    <div class="container-fluid">
        <div class="row">
        <!-- Sidebar -->
        <?php include('common/sidebar.php'); ?>
    <div class="container">
        <h1>Bulk Leads Upload</h1>
        
        <!-- Assign Leads Section -->
        <div class="section">
            <h2>Assign Leads to Users</h2>
            <p>Click the button below to assign all new leads to available users.</p>
            <form method="post">
                <button type="submit" name="assign_leads" class="btn btn-success">Assign Leads to Users</button>
            </form>
        </div>

        <!-- Download Sample CSV Section -->
        <div class="section">
            <h2>Download Sample CSV</h2>
            <p>Download the sample CSV file with proper format for bulk upload.</p>
            <form method="post">
                <button type="submit" name="download_sample" class="btn btn-primary">Download Sample CSV</button>
            </form>
        </div>

        <!-- Upload CSV Section -->
        <div class="section">
            <h2>Upload CSV File</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="csv_file" accept=".csv" required>
                <button type="submit" name="upload_csv" class="btn btn-primary">Upload CSV</button>
            </form>
            <div>
                <p><strong>For Source: </strong></p>
                <p>Put<strong> JD</strong> in source column in csv file for justdail</p>
                <p>Put<strong> GMB</strong> in source column in csv file for Google My Business</p>
                <p>Put<strong> IM</strong> in source column in csv file for IndiaMart</p>
                <p>Put<strong> SH</strong> in source column in csv file for Shulekha</p>
            </div>
        </div>

        <?php
        // Handle CSV Upload
        if (isset($_POST['upload_csv'])) {
            handleCSVUpload($conn);
        }
        ?>
    </div>
</body>
</html>

<?php
function downloadSampleCSV() {
    // Clear all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sample_leads.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, ['name', 'email', 'phoneno', 'address', 'city', 'state', 'category', 'pincode', 'source']);
    
    fclose($output);
    exit();
}

function handleCSVUpload($conn) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $fileName = $_FILES['csv_file']['tmp_name'];
        $fileExtension = pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION);
        
        if (strtolower($fileExtension) != 'csv') {
            echo "<div class='error'>Please upload a valid CSV file.</div>";
            return;
        }
        
        $file = fopen($fileName, 'r');
        
        // Skip header row
        fgetcsv($file);
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        
        while (($row = fgetcsv($file)) !== FALSE) {
            if (count($row) >= 6) {
                $name = mysqli_real_escape_string($conn, trim($row[0]));
                $email = mysqli_real_escape_string($conn, trim($row[1]));
                $phoneno = mysqli_real_escape_string($conn, trim($row[2]));
                $address = mysqli_real_escape_string($conn, trim($row[3]));
                $city = mysqli_real_escape_string($conn, trim($row[4]));
                $state = mysqli_real_escape_string($conn, trim($row[5]));
                $category = mysqli_real_escape_string($conn, trim($row[6]));
                $pincode = mysqli_real_escape_string($conn, trim($row[7]));
                $source = mysqli_real_escape_string($conn, trim($row[8]));
                
                // Validate required fields
                if (empty($name) || empty($phoneno)) {
                    $errors[] = "Row skipped: Name and Phone are required. Data: " . implode(', ', $row);
                    $errorCount++;
                    continue;
                }
                
                // Check for duplicate phone number
                $checkQuery = "SELECT id FROM leads WHERE phoneno = '$phoneno' AND is_deleted = 0";
                $checkResult = mysqli_query($conn, $checkQuery);
                
                // if (mysqli_num_rows($checkResult) > 0) {
                //     $errors[] = "Duplicate phone number: $phoneno for $name";
                //     $errorCount++;
                //     continue;
                // }
                
                // If source is empty in CSV, set it to empty instead of 'bulk_upload'
                if (empty($source)) {
                    $source = ''; // Set empty instead of default value
                }
                
                // Insert into database - source will be whatever is in CSV or empty
                $query = "INSERT INTO leads (name, email, phoneno, address, city, state, category, pincode, source, created_by, updated_by) 
                         VALUES ('$name', '$email', '$phoneno', '$address', '$city', '$state', '$category', '$pincode', '$source', 1, 1)";
                
                if (mysqli_query($conn, $query)) {
                    $successCount++;
                } else {
                    $errors[] = "Error inserting: " . mysqli_error($conn) . " - Data: " . implode(', ', $row);
                    $errorCount++;
                }
            } else {
                $errors[] = "Invalid row format: " . implode(', ', $row);
                $errorCount++;
            }
        }
        
        fclose($file);
        
        // Display results
        echo "<div class='success'>Successfully imported: $successCount leads</div>";
        if ($errorCount > 0) {
            echo "<div class='error'>Failed to import: $errorCount leads</div>";
            echo "<div style='max-height: 200px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
            echo "<strong>Errors:</strong><br>";
            foreach ($errors as $error) {
                echo "- " . htmlspecialchars($error) . "<br>";
            }
            echo "</div>";
        }
    } else {
        echo "<div class='error'>Please select a CSV file to upload.</div>";
    }
}

function assignLeadsToUsers($conn) {
    // Get all new leads (status = 'new')
    $leadsQuery = "SELECT id FROM leads WHERE status = 'new' AND is_deleted = 0 AND assigned_to IS NULL";
    $leadsResult = mysqli_query($conn, $leadsQuery);
    
    if (!$leadsResult) {
        echo "<div class='error'>Error fetching leads: " . mysqli_error($conn) . "</div>";
        return;
    }
    
    $newLeads = mysqli_fetch_all($leadsResult, MYSQLI_ASSOC);
    $totalLeads = count($newLeads);
    
    if ($totalLeads == 0) {
        echo "<div class='error'>No new leads found to assign.</div>";
        return;
    }
    
    // Get all active users with role = 1
    $usersQuery = "SELECT id FROM users WHERE role = '1' AND status = '1' AND is_deleted = 0";
    $usersResult = mysqli_query($conn, $usersQuery);
    
    if (!$usersResult) {
        echo "<div class='error'>Error fetching users: " . mysqli_error($conn) . "</div>";
        return;
    }
    
    $users = mysqli_fetch_all($usersResult, MYSQLI_ASSOC);
    $totalUsers = count($users);
    
    if ($totalUsers == 0) {
        echo "<div class='error'>No active users found to assign leads.</div>";
        return;
    }
    
    // Assign leads to users in round-robin fashion
    $assignedCount = 0;
    $userIndex = 0;
    
    foreach ($newLeads as $lead) {
        $leadId = $lead['id'];
        $userId = $users[$userIndex]['id'];
        
        // Update lead with assigned user
        $updateQuery = "UPDATE leads SET assigned_to = '$userId', updated_at = NOW() WHERE id = '$leadId'";
        
        if (mysqli_query($conn, $updateQuery)) {
            $assignedCount++;
        } else {
            echo "<div class='error'>Error assigning lead ID $leadId: " . mysqli_error($conn) . "</div>";
        }
        
        // Move to next user (round-robin)
        $userIndex = ($userIndex + 1) % $totalUsers;
    }
    
    echo "<div class='success'>Successfully assigned $assignedCount out of $totalLeads leads to $totalUsers users.</div>";
}
?>