<?php
/**
 * HTU COMPSSA CODEFEST 2025 - Audit Log Window
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
    
    $sql = "SELECT l.*, u.username 
            FROM audit_logs l 
            LEFT JOIN users u ON l.user_id = u.user_id 
            $where_clause 
            ORDER BY l.log_id DESC 
            LIMIT 500";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <pre id="detailsJson" class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;"></pre>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
        function viewDetails(log) {
            const details = {
                old: log.old_values ? JSON.parse(log.old_values) : null,
                new: log.new_values ? JSON.parse(log.new_values) : null,
                userAgent: log.user_agent
            };
            document.getElementById('detailsJson').textContent = JSON.serialize ? JSON.serialize(details, null, 2) : JSON.stringify(details, null, 2);
            detailsModal.show();
        }
    </script>
</body>
</html>
