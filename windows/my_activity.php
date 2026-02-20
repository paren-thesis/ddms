<?php
/**
 * HTU COMPSSA DDMS - My Activity
 * Individual user activity history
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$logs = [];

// Handle filters
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');

try {
    $pdo = getDBConnection();
    
    $where_conditions = ["user_id = ?"];
    $params = [$user_id];
    
    if (!empty($date_from)) {
        $where_conditions[] = "created_at >= ?";
        $params[] = $date_from . " 00:00:00";
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "created_at <= ?";
        $params[] = $date_to . " 23:59:59";
    }
    
    $where_clause = "WHERE " . implode(' AND ', $where_conditions);
    
    // Pagination logic
    $records_per_page = 15;
    $current_page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($current_page - 1) * $records_per_page;
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) FROM audit_logs $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
    
    // Fetch logs
    $sql = "SELECT * FROM audit_logs 
            $where_clause 
            ORDER BY log_id DESC 
            LIMIT $records_per_page OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = 'Failed to fetch activity logs: ' . $e->getMessage();
}

// Log that the user viewed their activity
logActivity('VIEW_MY_ACTIVITY');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - My Activity</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .activity-card {
            border-left: 4px solid var(--blue);
            transition: transform 0.2s;
        }
        .activity-card:hover {
            transform: translateX(5px);
        }
        .badge-action {
            font-size: 0.8rem;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-2">
                    <img src="../assets/compssa_logo.png" alt="COMPSSA Logo" class="logo">
                </div>
                <div class="col-md-8 text-center">
                    <h1 class="h3 mb-0 text-white">My Activity Feed</h1>
                </div>
                <div class="col-md-2 text-end">
                    <a href="control.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content py-4">
        <div class="container">
            <!-- Filter Bar -->
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small text-muted">From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">To Date</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-1"></i>Filter
                                </button>
                                <?php if($date_from || $date_to): ?>
                                    <a href="my_activity.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 text-primary">
                                <i class="fas fa-history me-2"></i>Recent Activities
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4" style="width: 80px;">S/N</th>
                                            <th>Activity Description</th>
                                            <th>Location</th>
                                            <th class="text-end pe-4">Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($logs)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-5 text-muted">
                                                    <i class="fas fa-info-circle me-1"></i> No activity records found.
                                                </td>
                                            </tr>
                                        <?php else: 
                                            $sn = $offset + 1;
                                            foreach ($logs as $log): 
                                                // Human-friendly action text
                                                $action = str_replace('_', ' ', $log['action']);
                                                $action = ucwords(strtolower($action));
                                                
                                                // Match sample log style
                                                if ($action === 'View My Activity') $action = 'Viewed Activity Logs';
                                                if ($action === 'Open Dashboard') $action = 'Opened Dashboard';
                                        ?>
                                            <tr>
                                                <td class="ps-4 text-muted"><?php echo $sn++; ?></td>
                                                <td>
                                                    <div class="fw-bold"><?php echo sanitizeInput($action); ?></div>
                                                    <?php if($log['table_name']): ?>
                                                        <small class="text-muted">Target: <?php echo ucfirst($log['table_name']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border">
                                                        <?php echo sanitizeInput($log['ip_address']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <div class="small fw-bold"><?php echo date('d M, Y', strtotime($log['created_at'])); ?></div>
                                                    <div class="text-muted" style="font-size: 0.75rem;"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php 
                                $query_params = $_GET;
                                unset($query_params['page']);
                            ?>
                            <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link shadow-sm" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $current_page - 1])); ?>">
                                    <i class="fas fa-chevron-left me-1"></i>Previous
                                </a>
                            </li>
                            <li class="page-item disabled">
                                <span class="page-link text-dark bg-white border-0 mx-2">
                                    Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                                </span>
                            </li>
                            <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link shadow-sm" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $current_page + 1])); ?>">
                                    Next<i class="fas fa-chevron-right ms-1"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="text-center py-4 text-muted small">
        &copy; <?php echo date('Y'); ?> HTU COMPSSA DDMS - Dues Data Management System
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
