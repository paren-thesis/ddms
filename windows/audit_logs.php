<?php
/**
 * HTU COMPSSA DDMS - Audit Log Window
 * View and manage system audit trails
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and has permission
if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'hod', 'supervisor'])) {
    redirect('control.php'); // Only authorized roles can view audit logs
}

$error_message = '';
$logs = [];

// Handle filters
$action_filter = sanitizeInput($_GET['action_filter'] ?? '');
$user_filter = sanitizeInput($_GET['user_filter'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');

try {
    $pdo = getDBConnection();
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($action_filter)) {
        $where_conditions[] = "l.action = ?";
        $params[] = $action_filter;
    }
    
    if (!empty($user_filter)) {
        $where_conditions[] = "u.username LIKE ?";
        $params[] = "%$user_filter%";
    }
    
    if (!empty($date_from)) {
        $where_conditions[] = "l.created_at >= ?";
        $params[] = $date_from . " 00:00:00";
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "l.created_at <= ?";
        $params[] = $date_to . " 23:59:59";
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Pagination logic
    $records_per_page = 30;
    $current_page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($current_page - 1) * $records_per_page;
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) 
                  FROM audit_logs l 
                  LEFT JOIN users u ON l.user_id = u.user_id 
                  $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
    
    $sql = "SELECT l.*, u.username 
            FROM audit_logs l 
            LEFT JOIN users u ON l.user_id = u.user_id 
            $where_clause 
            ORDER BY l.log_id DESC 
            LIMIT $records_per_page OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(); // Corrected from $count_stmt->fetchAll()
    
    // Log that audit logs window was viewed
    logActivity('VIEW_AUDIT_LOGS');

    // Get unique actions for filter
    $actions_stmt = $pdo->query("SELECT DISTINCT action FROM audit_logs");
    $all_actions = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $error_message = 'Failed to fetch audit logs: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Audit Logs</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <header class="app-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-2">
                    <img src="../assets/compssa_logo.png" alt="COMPSSA Logo" class="logo">
                </div>
                <div class="col-md-8 text-center">
                    <h1 class="app-title"><?php echo APP_NAME; ?></h1>
                </div>
                <div class="col-md-2 text-end">
                    <a href="control.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back to Control
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h2 class="text-center mb-4" style="color: var(--blue); font-size: 28px; font-weight: bold;">
                        System Audit Logs
                    </h2>

                    <!-- Filter Section -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Action</label>
                                    <select name="action_filter" class="form-select">
                                        <option value="">All Actions</option>
                                        <?php foreach ($all_actions as $action): ?>
                                            <option value="<?php echo $action; ?>" <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                                                <?php echo $action; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">User</label>
                                    <input type="text" name="user_filter" class="form-control" value="<?php echo $user_filter; ?>" placeholder="Username...">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">From</label>
                                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">To</label>
                                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Logs Table -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Timestamp</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Table</th>
                                            <th>Record ID</th>
                                            <th>IP Address</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($logs)): ?>
                                            <tr><td colspan="8" class="text-center">No logs found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($logs as $log): ?>
                                                <tr>
                                                    <td><?php echo $log['log_id']; ?></td>
                                                    <td><?php echo date('d M Y H:i', strtotime($log['created_at'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?php echo sanitizeInput($log['username'] ?? 'System/Guest'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info text-dark">
                                                            <?php echo sanitizeInput($log['action']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo sanitizeInput($log['table_name'] ?? '-'); ?></td>
                                                    <td><?php echo sanitizeInput($log['record_id'] ?? '-'); ?></td>
                                                    <td><small><?php echo sanitizeInput($log['ip_address']); ?></small></td>
                                                    <td>
                                                        <?php if ($log['new_values'] || $log['old_values']): ?>
                                                            <button class="btn btn-xs btn-link p-0" onclick='viewDetails(<?php echo json_encode($log); ?>)'>
                                                                <i class="fas fa-info-circle"></i> View
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination Navigation -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php 
                                        $query_params = $_GET;
                                        unset($query_params['page']); // Clear existing page
                                    ?>
                                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                        <?php 
                                            $prev_params = $query_params;
                                            $prev_params['page'] = $current_page - 1;
                                        ?>
                                        <a class="page-link" href="?<?php echo http_build_query($prev_params); ?>">
                                            <i class="fas fa-chevron-left me-1"></i> Previous
                                        </a>
                                    </li>

                                    <li class="page-item disabled">
                                        <span class="page-link text-dark bg-light px-4">
                                            Page <strong><?php echo $current_page; ?></strong> of <strong><?php echo $total_pages; ?></strong>
                                            <span class="ms-2 text-muted small">(<?php echo $total_records; ?> total)</span>
                                        </span>
                                    </li>

                                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <?php 
                                            $next_params = $query_params;
                                            $next_params['page'] = $current_page + 1;
                                        ?>
                                        <a class="page-link" href="?<?php echo http_build_query($next_params); ?>">
                                            Next <i class="fas fa-chevron-right ms-1"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Activity Detail Comparison</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 30%;">Field Name</th>
                                    <th style="width: 35%;">Previous Value</th>
                                    <th style="width: 35%;">New Value</th>
                                </tr>
                            </thead>
                            <tbody id="comparisonTableBody">
                                <!-- Data injected here -->
                            </tbody>
                        </table>
                    </div>
                    <div id="noDataMessage" class="p-4 text-center text-muted d-none">
                        No field-level changes recorded for this action.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <div class="small text-muted w-100 mb-2">
                        <i class="fas fa-desktop me-1"></i> Device: <span id="logUserAgent" class="text-truncate d-inline-block" style="max-width: 600px;"></span>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
        
        function viewDetails(log) {
            const tbody = document.getElementById('comparisonTableBody');
            const noDataMsg = document.getElementById('noDataMessage');
            const userAgentEl = document.getElementById('logUserAgent');
            
            tbody.innerHTML = '';
            userAgentEl.textContent = log.user_agent || 'Unknown';
            
            let oldVals = {};
            let newVals = {};
            
            try {
                oldVals = log.old_values ? JSON.parse(log.old_values) : {};
                newVals = log.new_values ? JSON.parse(log.new_values) : {};
            } catch(e) { console.error("Parse error", e); }

            // Collect all unique keys from both objects
            const allKeys = [...new Set([...Object.keys(oldVals), ...Object.keys(newVals)])];
            
            if (allKeys.length === 0) {
                noDataMsg.classList.remove('d-none');
                tbody.closest('table').classList.add('d-none');
            } else {
                noDataMsg.classList.add('d-none');
                tbody.closest('table').classList.remove('d-none');
                
                allKeys.forEach(key => {
                    const oldV = oldVals[key] !== undefined ? oldVals[key] : '-';
                    const newV = newVals[key] !== undefined ? newVals[key] : '-';
                    
                    // Simple formatting for dates or nested objects
                    const formatVal = (v) => {
                        if (v === null || v === undefined) return '-';
                        if (typeof v === 'object') return JSON.stringify(v);
                        return v;
                    };

                    const row = document.createElement('tr');
                    const isChanged = formatVal(oldV) !== formatVal(newV);
                    
                    row.innerHTML = `
                        <td class="fw-bold text-muted">${key.replace(/_/g, ' ').toUpperCase()}</td>
                        <td class="text-danger small">${formatVal(oldV)}</td>
                        <td class="${isChanged ? 'text-success fw-bold' : 'text-dark'} small">${formatVal(newV)}</td>
                    `;
                    tbody.appendChild(row);
                });
            }
            
            detailsModal.show();
        }
    </script>
</body>
</html>
