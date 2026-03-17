<!-- Filters Section -->
<div class="filter-section">
    <form method="GET" class="row g-3 align-items-end">
        <input type="hidden" name="type" value="<?php echo $lead_type; ?>">
        
        <div class="col-md-2">
            <label class="form-label">Start Date</label>
            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label">End Date</label>
            <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label">Lead Type</label>
            <select class="form-select" name="filter_lead_type">
                <option value="all">All Types</option>
                <option value="hot" <?php echo ($filter_lead_type == 'hot') ? 'selected' : ''; ?>>Hot</option>
                <option value="warm" <?php echo ($filter_lead_type == 'warm') ? 'selected' : ''; ?>>Warm</option>
                <option value="cold" <?php echo ($filter_lead_type == 'cold') ? 'selected' : ''; ?>>Cold</option>
            </select>
        </div>
        
        <?php if ($isSuperAdmin): ?>
        <div class="col-md-3">
            <label class="form-label">Assigned To</label>
            <select class="form-select" name="assigned_user">
                <option value="all">All Users</option>
                <option value="unassigned" <?php echo ($assigned_user == 'unassigned') ? 'selected' : ''; ?>>Unassigned</option>
                <?php if (count($assigned_users_filter) > 0): ?>
                    <?php foreach ($assigned_users_filter as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo ($assigned_user == $user['id']) ? 'selected' : ''; ?>>
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
        <h5 class="mb-0"><?php echo $page_title; ?> (<?php echo count($all_leads_data); ?>)</h5>
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
                        ?>
                            <tr class="<?php echo $is_duplicate ? 'duplicate-phone' : ''; ?>">
                                <?php if ($isSuperAdmin): ?>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input lead-checkbox" type="checkbox" value="<?php echo $lead['id']; ?>">
                                    </div>
                                </td>
                                <?php endif; ?>
                                <td><?php echo $lead['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($lead['name']); ?></strong>
                                </td>
                                <td>
                                    <span class="phone-link" onclick="window.location.href='leads_management.php?type=duplicate&phone=<?php echo urlencode($lead['phoneno']); ?>'">
                                        <?php echo htmlspecialchars($lead['phoneno']); ?>
                                        <?php if ($is_duplicate): ?>
                                            <span class="duplicate-count" title="<?php echo $duplicate_phones[$lead['phoneno']]; ?> duplicates">
                                                <?php echo $duplicate_phones[$lead['phoneno']]; ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($lead['email']); ?></td>
                                <td>
                                    <span class="badge status-badge badge-<?php echo $lead['type']; ?>">
                                        <?php echo ucfirst($lead['type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge status-badge badge-<?php echo $lead['status']; ?>">
                                        <?php echo ucfirst($lead['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($lead['source']); ?></td>
                                <?php if ($isSuperAdmin): ?>
                                <td>
                                    <?php if (!empty($lead['assigned_user_name'])): ?>
                                        <?php echo htmlspecialchars($lead['assigned_user_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td><?php echo date('M j, Y', strtotime($lead['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="openCommentPopup(<?php echo $lead['id']; ?>, '<?php echo $lead['status']; ?>')"
                                                title="Update Status & Comment">
                                            <i class="fas fa-comment"></i>
                                        </button>
                                        <a href="view_lead.php?id=<?php echo $lead['id']; ?>" 
                                           class="btn btn-outline-info" title="View Lead">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_lead.php?id=<?php echo $lead['id']; ?>" 
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
        <?php else: ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-4x mb-3"></i>
                <h4>No Leads Found</h4>
                <p>No <?php echo strtolower($page_title); ?> found with the current filters.</p>
                <a href="leads_management.php?type=<?php echo $lead_type; ?>" class="btn btn-primary">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>
</div>