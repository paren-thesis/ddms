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
if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['administrator', 'supervisor', 'lecturer'])) {
    redirect('login.php');
}

$error_message = '';
$success_message = '';
$summary = [];

// Handle export (CSV)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportCSV();
}

function exportCSV() {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="student_payment_summary.csv"');
    $output = fopen('php://output', 'w');
    $pdo = getDBConnection();
    $sql = "SELECT s.index_no, CONCAT(s.first_name, ' ', s.last_name) AS name, s.email, s.current_academic_year, p.programme_name, s.programme_level, COALESCE(SUM(pay.amount_paid), 0) as total_paid, COUNT(pay.payment_id) as payment_count, GROUP_CONCAT(pay.receipt_no SEPARATOR ', ') as receipt_nos, s.created_at 
            FROM students s 
            LEFT JOIN programmes p ON s.programme_id = p.programme_id 
            LEFT JOIN payments pay ON s.student_id = pay.student_id 
            WHERE s.deleted_at IS NULL
            GROUP BY s.student_id, s.index_no, s.first_name, s.last_name, s.email, s.current_academic_year, p.programme_name, s.programme_level, s.created_at";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
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
    
    // Original payment summary fetch
    $sql = "SELECT s.index_no, s.first_name, s.last_name, s.email, s.current_academic_year, p.programme_name, s.programme_level, COALESCE(SUM(pay.amount_paid), 0) as total_paid, COUNT(pay.payment_id) as payment_count, GROUP_CONCAT(pay.receipt_no SEPARATOR ', ') as receipt_nos, s.created_at 
            FROM students s 
            LEFT JOIN programmes p ON s.programme_id = p.programme_id 
            LEFT JOIN payments pay ON s.student_id = pay.student_id 
            WHERE s.deleted_at IS NULL
            GROUP BY s.student_id, s.index_no, s.first_name, s.last_name, s.email, s.current_academic_year, p.programme_name, s.programme_level, s.created_at 
            ORDER BY s.first_name, s.last_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $summary = $stmt->fetchAll();
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
                    <img src="../assets/Logo_Worldskills_Ghana.png" alt="HTU Logo" class="logo">
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
                    
                    <!-- Export Button -->
                    <div class="mb-4 text-end">
                        <a href="?export=csv" class="btn btn-primary">
                            <i class="fas fa-file-csv me-2"></i>Export CSV
                        </a>
                    </div>
                    
                    <!-- Payment Summary Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-table me-2"></i>Student Payment Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
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
                    labels: <?php echo json_encode(array_column($programme_stats, 'programme_code')); ?>,
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
</body>
</html>