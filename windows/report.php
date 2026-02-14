<?php
/**
 * HTU COMPSSA CODEFEST 2025 - Report Window
 * Reporting and analytics for dues and students
 * 
 * Features:
 * - Generate payment summaries
 * - Student statistics
 * - Filter and export data
 * - Visualize analytics
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and has permission
if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'hod', 'cashier', 'supervisor'])) {
    redirect('login.php');
}

$error_message = '';
$success_message = '';
// Get filter parameters
$search = sanitizeInput($_GET['search'] ?? '');
$programme_filter = sanitizeInput($_GET['programme'] ?? '');
$year_filter = sanitizeInput($_GET['year'] ?? '');
$level_filter = sanitizeInput($_GET['level'] ?? '');
$session_filter = sanitizeInput($_GET['session'] ?? '');

// Handle export (CSV)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportCSV();
}

function exportCSV() {
    global $search, $programme_filter, $year_filter, $level_filter, $session_filter;
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="student_payment_summary.csv"');
    $output = fopen('php://output', 'w');
    $pdo = getDBConnection();
    
    // Header
    fputcsv($output, ['Index No', 'Name', 'Email', 'Academic Year', 'Programme', 'Level', 'Total Paid', 'Payment Count', 'Receipts', 'Date Added']);
    
    $where_conditions = ["s.deleted_at IS NULL"];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(s.index_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
    if (!empty($programme_filter)) { $where_conditions[] = "s.programme_id = ?"; $params[] = $programme_filter; }
    if (!empty($year_filter)) { $where_conditions[] = "s.current_academic_year = ?"; $params[] = $year_filter; }
    if (!empty($level_filter)) { $where_conditions[] = "s.programme_level = ?"; $params[] = $level_filter; }
    if (!empty($session_filter)) { $where_conditions[] = "s.session_type = ?"; $params[] = $session_filter; }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "SELECT s.index_no, CONCAT(s.first_name, ' ', s.last_name) AS name, s.email, s.current_academic_year, p.programme_name, s.programme_level, COALESCE(SUM(pay.amount_paid), 0) as total_paid, COUNT(pay.payment_id) as payment_count, GROUP_CONCAT(pay.receipt_no SEPARATOR ', ') as receipt_nos, s.created_at 
            FROM students s 
            LEFT JOIN programmes p ON s.programme_id = p.programme_id 
            LEFT JOIN payments pay ON s.student_id = pay.student_id 
            $where_clause
            GROUP BY s.student_id, s.index_no, s.first_name, s.last_name, s.email, s.current_academic_year, p.programme_name, s.programme_level, s.created_at
            ORDER BY s.first_name, s.last_name";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [$row['index_no'], $row['name'], $row['email'], $row['current_academic_year'], $row['programme_name'], $row['programme_level'], $row['total_paid'], $row['payment_count'], $row['receipt_nos'], $row['created_at']]);
    }
    fclose($output);
    exit();
}

// Fetch payment summary
try {
    $pdo = getDBConnection();
    
    // 1. Student Enrollment by Programme
    $stmt = $pdo->query("SELECT p.programme_code, COUNT(s.student_id) as count 
                         FROM programmes p 
                         LEFT JOIN students s ON p.programme_id = s.programme_id AND s.deleted_at IS NULL 
                         GROUP BY p.programme_id, p.programme_code 
                         ORDER BY count DESC");
    $programme_stats = $stmt->fetchAll();
    
    // 2. Student Distribution by Level
    $stmt = $pdo->query("SELECT programme_level as level, COUNT(*) as count 
                         FROM students 
                         WHERE deleted_at IS NULL 
                         GROUP BY level 
                         ORDER BY level ASC");
    $level_stats = $stmt->fetchAll();
    
    // Unified where conditions for payment summary
    $where_conditions = ["s.deleted_at IS NULL"];
    $sum_params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(s.index_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ?)";
        $search_param = "%$search%";
        $sum_params = array_merge($sum_params, [$search_param, $search_param, $search_param, $search_param]);
    }
    if (!empty($programme_filter)) { $where_conditions[] = "s.programme_id = ?"; $sum_params[] = $programme_filter; }
    if (!empty($year_filter)) { $where_conditions[] = "s.current_academic_year = ?"; $sum_params[] = $year_filter; }
    if (!empty($level_filter)) { $where_conditions[] = "s.programme_level = ?"; $sum_params[] = $level_filter; }
    if (!empty($session_filter)) { $where_conditions[] = "s.session_type = ?"; $sum_params[] = $session_filter; }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Pagination logic
    $records_per_page = 15;
    $current_page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($current_page - 1) * $records_per_page;
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(DISTINCT s.student_id) FROM students s LEFT JOIN payments pay ON s.student_id = pay.student_id $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($sum_params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
    
    // Fetch summary data with filters and pagination
    $sql = "SELECT s.index_no, s.first_name, s.last_name, s.email, s.current_academic_year, p.programme_name, s.programme_level, COALESCE(SUM(pay.amount_paid), 0) as total_paid, COUNT(pay.payment_id) as payment_count, GROUP_CONCAT(pay.receipt_no SEPARATOR ', ') as receipt_nos, s.created_at 
            FROM students s 
            LEFT JOIN programmes p ON s.programme_id = p.programme_id 
            LEFT JOIN payments pay ON s.student_id = pay.student_id 
            $where_clause
            GROUP BY s.student_id, s.index_no, s.first_name, s.last_name, s.email, s.current_academic_year, p.programme_name, s.programme_level, s.created_at 
            ORDER BY s.first_name, s.last_name
            LIMIT $records_per_page OFFSET $offset";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($sum_params);
    $summary = $stmt->fetchAll();
    
    // Get programmes for filter
    $stmt = $pdo->query("SELECT programme_id, programme_name FROM programmes WHERE status = 'Active' ORDER BY programme_name");
    $programmes = $stmt->fetchAll();

    // Get unique academic years for filter
    $stmt = $pdo->query("SELECT DISTINCT academic_year FROM academic_sessions ORDER BY academic_year DESC");
    $academic_years = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $error_message = 'Failed to fetch report: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Reports</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Header with Logo and Title -->
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

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h2 class="text-center mb-4" style="color: var(--blue); font-size: 28px; font-weight: bold;">
                        Reports & Analytics
                    </h2>
                    
                    <!-- Display Messages -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <!-- Analytics Section -->
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Programme Enrollment</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="programmeChart" style="max-height: 250px;"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Level Breakdown</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="levelChart" style="max-height: 250px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search and Filters Section -->
                    <div class="card mb-4">
                        <div class="card-header pb-0">
                            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Student Payments</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="search" class="form-label">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               value="<?php echo $search; ?>" placeholder="Index, Name, Email...">
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="programme" class="form-label">Programme</label>
                                    <select class="form-control" id="programme" name="programme">
                                        <option value="">All Programmes</option>
                                        <?php foreach ($programmes as $prog): ?>
                                            <option value="<?php echo $prog['programme_id']; ?>" 
                                                    <?php echo $programme_filter == $prog['programme_id'] ? 'selected' : ''; ?>>
                                                <?php echo $prog['programme_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="level" class="form-label">Level</label>
                                    <select class="form-control" id="level" name="level">
                                        <option value="">All Levels</option>
                                        <option value="100" <?php echo $level_filter == '100' ? 'selected' : ''; ?>>100</option>
                                        <option value="200" <?php echo $level_filter == '200' ? 'selected' : ''; ?>>200</option>
                                        <option value="300" <?php echo $level_filter == '300' ? 'selected' : ''; ?>>300</option>
                                        <option value="400" <?php echo $level_filter == '400' ? 'selected' : ''; ?>>400</option>
                                        <option value="Top-Up" <?php echo $level_filter == 'Top-Up' ? 'selected' : ''; ?>>Top-Up</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="session" class="form-label">Session</label>
                                    <select class="form-control" id="session" name="session">
                                        <option value="">All Sessions</option>
                                        <option value="Regular" <?php echo $session_filter == 'Regular' ? 'selected' : ''; ?>>Regular</option>
                                        <option value="Weekend" <?php echo $session_filter == 'Weekend' ? 'selected' : ''; ?>>Weekend</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end mb-3">
                                    <div class="d-grid w-100">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-sync-alt me-1"></i>Apply Filters
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-12 text-end">
                            <button onclick="window.print()" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-print me-2"></i>Print Report
                            </button>
                            <?php 
                                $export_params = $_GET;
                                $export_params['export'] = 'csv';
                            ?>
                            <a href="?<?php echo http_build_query($export_params); ?>" class="btn btn-success">
                                <i class="fas fa-file-csv me-2"></i>Export CSV
                            </a>
                        </div>
                    </div>
                    
                    <!-- Payment Summary Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-table me-2"></i>Student Payment Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="reportTable">
                                    <thead>
                                        <tr>
                                            <th>Index No</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Year</th>
                                            <th>Programme</th>
                                            <th>Level</th>
                                            <th>Total Paid</th>
                                            <th>Count</th>
                                            <th>Receipts</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($summary)): ?>
                                            <tr>
                                                <td colspan="10" class="text-center">No data found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($summary as $row): ?>
                                                <tr>
                                                    <td><?php echo sanitizeInput($row['index_no']); ?></td>
                                                    <td><?php echo sanitizeInput($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                    <td><?php echo sanitizeInput($row['email']); ?></td>
                                                    <td><?php echo sanitizeInput($row['current_academic_year']); ?></td>
                                                    <td><?php echo sanitizeInput($row['programme_name']); ?></td>
                                                    <td><?php echo sanitizeInput($row['programme_level']); ?></td>
                                                    <td><?php echo formatCurrency($row['total_paid']); ?></td>
                                                    <td><?php echo sanitizeInput($row['payment_count']); ?></td>
                                                    <td><small><?php echo sanitizeInput($row['receipt_nos'] ?: 'N/A'); ?></small></td>
                                                    <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination Navigation -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4 no-print">
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
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Programme Enrollment Bar Chart
            const progCtx = document.getElementById('programmeChart').getContext('2d');
            new Chart(progCtx, {
                type: 'bar',
                data: {
                    labels: <?php 
                        $labels = array_map(function($s) {
                            return str_replace('-', ' ', $s['programme_code']);
                        }, $programme_stats);
                        echo json_encode($labels); 
                    ?>,
                    datasets: [{
                        label: 'Number of Students',
                        data: <?php echo json_encode(array_column($programme_stats, 'count')); ?>,
                        backgroundColor: '#050589',
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });

            // Level Distribution Pie Chart
            const levelCtx = document.getElementById('levelChart').getContext('2d');
            new Chart(levelCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_map(function($l) { return "Level " . $l['level']; }, $level_stats)); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($level_stats, 'count')); ?>,
                        backgroundColor: [
                            '#050589', '#FF8B00', '#F5D200', '#0a0a0a', '#cccccc'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'right' }
                    }
                }
            });
        });
    </script>
    <style>
        @media print {
            .app-header, .mb-4, .input-group, #search, .btn, .col-md-2.text-end, .card-header, nav.no-print { display: none !important; }
            .card { border: none !important; }
            .card-header:not(.no-print) { background-color: #f8f9fa !important; border: 1px solid #dee2e6 !important; display: block !important; }
            body { background: white !important; }
            .row.mb-5 { display: flex !important; margin-bottom: 2rem !important; }
            .col-md-6 { width: 50% !important; float: left !important; }
        }
    </style>
</body>
</html>