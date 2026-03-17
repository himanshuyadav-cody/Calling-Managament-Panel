<?php
// regular_leads_view.php
// This file contains the layout for regular leads

// Check if variables are set, if not set default values
if (!isset($all_leads_data)) {
    $all_leads_data = [];
}
if (!isset($duplicate_phones)) {
    $duplicate_phones = [];
}
if (!isset($assigned_users_filter)) {
    $assigned_users_filter = [];
}
if (!isset($users)) {
    $users = [];
}
?>

<!-- Filters Section -->
<div class="filter-section">
    <form method="GET" class="row g-3 align-items-end">
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($lead_type); ?>">
        
        <div class="col-md-2">
            <label class="form-label">Start Date</label>
            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date ?? ''); ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label">End Date</label>
            <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date ?? ''); ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label">Lead Type</label>
            <select class="form-select" name="filter_lead_type">
                <option value="all">All Types</option>
                <option value="hot" <?php echo (($filter_lead_type ?? '') == 'hot') ? 'selected' : ''; ?>>Hot</option>
                <option value="warm" <?php echo (($filter_lead_type ?? '') == 'warm') ? 'selected' : ''; ?>>Warm</option>
                <option value="cold" <?php echo (($filter_lead_type ?? '') == 'cold') ? 'selected' : ''; ?>>Cold</option>
            </select>
        </div>
        
        <?php if ($isSuperAdmin): ?>
        <div class="col-md-3">
            <label class="form-label">Assigned To</label>
            <select class="form-select" name="assigned_user">
                <option value="all">All Users</option>
                <option value="unassigned" <?php echo (($assigned_user ?? '') == 'unassigned') ? 'selected' : ''; ?>>Unassigned</option>
                <?php if (count($assigned_users_filter) > 0): ?>
                    <?php foreach ($assigned_users_filter as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo (($assigned_user ?? '') == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
            <a href="leads_management.php?type=<?php echo $lead_type; ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<?php if ($isSuperAdmin): ?>
<!-- Assign Leads Section -->
<div class="assign-section">
    <form method="POST" id="assignForm">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Assign to User</label>
                <select class="form-select" name="assign_user_id" id="assignUserId" required>
                    <option value="">Select User...</option>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Select Leads</label>
                <select class="form-select" id="selectCount" onchange="selectLeads()">
                    <option value="10">Select 10 Leads</option>
                    <option value="25">Select 25 Leads</option>
                    <option value="50">Select 50 Leads</option>
                    <option value="all">Select All Leads</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <button type="submit" name="assign_to_user" value="1" class="btn btn-success">Assign Selected Leads</button>
            </div>
            
            <div class="col-md-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAll">
                    <label class="form-check-label" for="selectAll">Select All</label>
                </div>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Leads Table -->
<div class="card">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo $page_title ?? 'Leads'; ?> (<?php echo count($all_leads_data); ?>)</h5>
        <?php if (!empty($duplicate_phones)): ?>
            <span class="badge bg-warning text-dark">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo count($duplicate_phones); ?> Duplicate Numbers
            </span>
        <?php endif; ?>
    </div>
    
    <div class="card-body">
        <?php if (!empty($all_leads_data)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="leadsTable">
                    <thead class="table-dark">
                        <tr>
                            <?php if ($isSuperAdmin): ?>
                            <th width="50">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="tableSelectAll">
                                </div>
                            </th>
                            <?php endif; ?>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Source</th>
                            <?php if ($isSuperAdmin): ?>
                            <th>Assigned To</th>
                            <?php endif; ?>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_leads_data as $lead): 
                            $is_duplicate = isset($duplicate_phones[$lead['phoneno']]);
                            // Safely access array elements
                            $lead_id = $lead['id'] ?? '';
                            $lead_name = $lead['name'] ?? '';
                            $lead_phone = $lead['phoneno'] ?? '';
                            $lead_email = $lead['email'] ?? '';
                            $lead_type_val = $lead['type'] ?? '';
                            $lead_status = $lead['status'] ?? '';
                            $lead_source = $lead['source'] ?? '';
                            $lead_created = $lead['created_at'] ?? '';
                            $assigned_user_name = $lead['assigned_user_name'] ?? '';
                        ?>
                            <tr class="<?php echo $is_duplicate ? 'duplicate-phone' : ''; ?>">
                                <?php if ($isSuperAdmin): ?>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input lead-checkbox" type="checkbox" value="<?php echo $lead_id; ?>">
                                    </div>
                                </td>
                                <?php endif; ?>
                                <td><?php echo $lead_id; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($lead_name); ?></strong>
                                </td>
                                <td>
                                    <span class="phone-link" onclick="window.location.href='leads_management.php?type=duplicate&phone=<?php echo urlencode($lead_phone); ?>'">
                                        <?php echo htmlspecialchars($lead_phone); ?>
                                        <?php if ($is_duplicate): ?>
                                            <span class="duplicate-count" title="<?php echo $duplicate_phones[$lead_phone]; ?> duplicates">
                                                <?php echo $duplicate_phones[$lead_phone]; ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($lead_email); ?></td>
                                <td>
                                    <span class="badge status-badge 
                                        <?php 
                                        switch($lead_type_val) {
                                            case 'hot': echo 'badge-hot'; break;
                                            case 'warm': echo 'badge-warm'; break;
                                            case 'cold': echo 'badge-cold'; break;
                                            default: echo 'badge-new';
                                        }
                                        ?>">
                                        <?php echo ucfirst($lead_type_val); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge status-badge 
                                        <?php 
                                        switch($lead_status) {
                                            case 'new': echo 'badge-new'; break;
                                            case 'contacted': echo 'badge-contacted'; break;
                                            case 'follow_up': echo 'badge-follow_up'; break;
                                            case 'confirmed': echo 'badge-confirmed'; break;
                                            case 'converted': echo 'badge-converted'; break;
                                            case 'rejected': echo 'badge-rejected'; break;
                                            case 'duplicate': echo 'badge-duplicate'; break;
                                            default: echo 'badge-new';
                                        }
                                        ?>">
                                        <?php echo ucfirst($lead_status); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($lead_source); ?></td>
                                <?php if ($isSuperAdmin): ?>
                                <td>
                                    <?php if (!empty($assigned_user_name)): ?>
                                        <?php echo htmlspecialchars($assigned_user_name); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <?php if (!empty($lead_created)): ?>
                                        <?php echo date('M j, Y', strtotime($lead_created)); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="openCommentPopup(<?php echo $lead_id; ?>, '<?php echo $lead_status; ?>')"
                                                title="Update Status & Comment">
                                            <i class="fas fa-comment"></i>
                                        </button>
                                        <a href="view_lead.php?id=<?php echo $lead_id; ?>" 
                                           class="btn btn-outline-info" title="View Lead">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_lead.php?id=<?php echo $lead_id; ?>" 
                                           class="btn btn-outline-secondary" title="Edit Lead">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- JavaScript for table functionality -->
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Select All functionality for table header
                const tableSelectAll = document.getElementById('tableSelectAll');
                if (tableSelectAll) {
                    tableSelectAll.addEventListener('change', function() {
                        const checkboxes = document.querySelectorAll('.lead-checkbox');
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = this.checked;
                        });
                    });
                }

                // Select All functionality for assign section
                const selectAll = document.getElementById('selectAll');
                if (selectAll) {
                    selectAll.addEventListener('change', function() {
                        const checkboxes = document.querySelectorAll('.lead-checkbox');
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = this.checked;
                        });
                    });
                }

                // Initialize DataTable if not already initialized
                if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#leadsTable')) {
                    $('#leadsTable').DataTable({
                        "pageLength": 25,
                        "order": [[1, 'desc']], // Order by ID descending
                        "language": {
                            "search": "Search all columns:",
                            "lengthMenu": "Show _MENU_ entries",
                            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                            "infoEmpty": "Showing 0 to 0 of 0 entries",
                            "infoFiltered": "(filtered from _MAX_ total entries)"
                        },
                        "columnDefs": [
                            <?php if ($isSuperAdmin): ?>
                            { "orderable": false, "targets": [0, 9] } // Make checkbox and actions columns not sortable
                            <?php else: ?>
                            { "orderable": false, "targets": [7] } // Make actions column not sortable
                            <?php endif; ?>
                        ]
                    });
                }
            });

            // Select specific number of leads
            function selectLeads() {
                const selectCount = document.getElementById('selectCount').value;
                const checkboxes = document.querySelectorAll('.lead-checkbox');
                const assignUserId = document.getElementById('assignUserId').value;
                
                if (!assignUserId) {
                    alert('Please select a user first.');
                    return;
                }
                
                // Uncheck all first
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                
                // Check specified number of leads
                let count = 0;
                if (selectCount === 'all') {
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = true;
                    });
                } else {
                    const maxCount = parseInt(selectCount);
                    checkboxes.forEach(checkbox => {
                        if (count < maxCount) {
                            checkbox.checked = true;
                            count++;
                        }
                    });
                }
            }

            // Handle form submission - collect checked lead IDs
            document.getElementById('assignForm')?.addEventListener('submit', function(e) {
                const checkboxes = document.querySelectorAll('.lead-checkbox:checked');
                const assignUserId = document.getElementById('assignUserId').value;
                
                if (checkboxes.length === 0) {
                    alert('Please select at least one lead to assign.');
                    e.preventDefault();
                    return;
                }
                
                if (!assignUserId) {
                    alert('Please select a user to assign leads to.');
                    e.preventDefault();
                    return;
                }
                
                // Add hidden inputs for each selected lead
                checkboxes.forEach(checkbox => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'lead_ids[]';
                    hiddenInput.value = checkbox.value;
                    this.appendChild(hiddenInput);
                });
            });
            </script>

        <?php else: ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-4x mb-3"></i>
                <h4>No Leads Found</h4>
                <p>No <?php echo strtolower($page_title ?? 'leads'); ?> found with the current filters.</p>
                <a href="leads_management.php?type=<?php echo $lead_type; ?>" class="btn btn-primary">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>
</div>