<?php
/**
 * HTU COMPSSA CODEFEST 2025 - Control Window
 * Main navigation hub for the application
 * 
 * Features:
 * - Links to other windows (Data, Payment, Report)
 * - Role-based menu access
 * - Option to close the application
 * - Logout functionality
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logActivity('LOGOUT');
    session_destroy();
    redirect('login.php');
}

// Log that dashboard was opened
logActivity('OPEN_DASHBOARD');

// Handle Password Change / Prompt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password_prompt') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $dont_show_again = isset($_POST['dont_show_again']) ? true : false;
    
    try {
        $pdo = getDBConnection();
        if ($dont_show_again) {
            // User opted to keep current password
            $stmt = $pdo->prepare("UPDATE users SET must_change_password = 0 WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $_SESSION['must_change_password'] = false;
            $success_message = "Preference saved. You can change your password later.";
        } elseif (!empty($new_password) && !empty($confirm_password)) {
            if ($new_password === $confirm_password) {
                $hash = hashPassword($new_password);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE user_id = ?");
                $stmt->execute([$hash, $_SESSION['user_id']]);
                $_SESSION['must_change_password'] = false;
                $success_message = "Password updated successfully.";
            } else {
                $error_message = "New passwords do not match.";
            }
        } else {
            $error_message = "Please enter and confirm your new password.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Get user information
$user_role = $_SESSION['user_role'] ?? '';
$username = $_SESSION['username'] ?? '';
$email = $_SESSION['email'] ?? '';

// Define role-based permissions
$role_permissions = [
    'admin' => ['data', 'payment', 'report', 'users', 'audit_logs', 'settings'],
    'hod' => ['data', 'payment', 'report', 'audit_logs', 'settings'],
    'cashier' => ['payment', 'report', 'settings'],
    'supervisor' => ['data', 'payment', 'report', 'audit_logs'],
    'student' => ['data', 'payment']
];

$user_permissions = $role_permissions[$user_role] ?? [];

// Fetch statistics
$total_students = 0;
$total_payments = 0;
$total_revenue = 0;
$academic_year = getCurrentAcademicYear();

try {
    $pdo = getDBConnection();
    
    // Total students (excluding soft-deleted)
    $stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL");
    $total_students = $stmt->fetchColumn();
    
    // Total payments
    $stmt = $pdo->query("SELECT COUNT(*) FROM payments");
    $total_payments = $stmt->fetchColumn();
    
    // Total revenue
    $stmt = $pdo->query("SELECT SUM(amount_paid) FROM payments");
    $total_revenue = $stmt->fetchColumn() ?: 0;
    
    // Monthly Revenue Trend (Last 6 Months)
    $stmt = $pdo->query("SELECT DATE_FORMAT(payment_date, '%b %Y') as month, SUM(amount_paid) as total 
                         FROM payments 
                         GROUP BY month 
                         ORDER BY payment_date DESC 
                         LIMIT 6");
    $revenue_data = array_reverse($stmt->fetchAll());
    
    // Payment Completion Rate (Simplified: Total Paid vs Total Dues for current session)
    // First, get total mandatory dues amount for current session
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM dues WHERE academic_year = ? AND is_mandatory = 1");
    $stmt->execute([$academic_year]);
    $avg_dues_per_student = $stmt->fetchColumn() ?: 0;
    $total_expected = $avg_dues_per_student * $total_students;
    
} catch (PDOException $e) {
    // Fail silently, stats will remain 0
    error_log("Failed to fetch statistics: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Control Panel</title>
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
                    <div class="user-info">
                        <span>Welcome, <?php echo sanitizeInput($username); ?></span>
                        <br>
                        <small><?php echo ucfirst($user_role); ?></small>
                    </div>
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
                        Control Panel
                    </h2>
                    
                    <!-- User Info Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>User Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Username:</strong> <?php echo sanitizeInput($username); ?></p>
                                    <p><strong>Email:</strong> <?php echo sanitizeInput($email); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Role:</strong> <?php echo ucfirst($user_role); ?></p>
                                    <p><strong>Login Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <?php if ($_SESSION['user_role'] !== 'student'): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Quick Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 text-center">
                                            <div class="stat-item">
                                                <i class="fas fa-users fa-2x mb-2" style="color: var(--blue);"></i>
                                                <h4><?php echo number_format($total_students); ?></h4>
                                                <p>Total Students</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <div class="stat-item">
                                                <i class="fas fa-credit-card fa-2x mb-2" style="color: var(--orange-brown);"></i>
                                                <h4><?php echo number_format($total_payments); ?></h4>
                                                <p>Total Payments</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <div class="stat-item">
                                                <i class="fas fa-money-bill-wave fa-2x mb-2" style="color: var(--yellow);"></i>
                                                <h4><?php echo formatCurrency($total_revenue); ?></h4>
                                                <p>Total Revenue</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <div class="stat-item">
                                                <i class="fas fa-calendar fa-2x mb-2" style="color: var(--blue);"></i>
                                                <h4><?php echo sanitizeInput($academic_year); ?></h4>
                                                <p>Academic Year</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Visual Analytics -->
                    <?php if ($_SESSION['user_role'] !== 'student'): ?>
                    <div class="row mb-5">
                        <div class="col-md-8">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Revenue Trend (Last 6 Months)</h5>
                                    <span class="badge bg-primary"><?php echo $academic_year; ?></span>
                                </div>
                                <div class="card-body">
                                    <canvas id="revenueChart" style="max-height: 250px;"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Payment Progress</h5>
                                </div>
                                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                                    <canvas id="completionChart" style="max-height: 200px;"></canvas>
                                    <div class="mt-3 text-center">
                                        <h4 class="mb-0" style="color: var(--blue);">
                                            <?php 
                                                $percentage = ($total_expected > 0) ? round(($total_revenue / $total_expected) * 100, 1) : 0;
                                                echo $percentage . '%';
                                            ?>
                                        </h4>
                                        <small class="text-muted">Expected: <?php echo formatCurrency($total_expected); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Navigation Cards -->
                    <div class="row">
                        <?php if (in_array('data', $user_permissions)): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-users me-2"></i>
                                        <?php echo ($_SESSION['user_role'] === 'student') ? 'My Academic Record' : 'Student Data'; ?>
                                    </h5>
                                </div>
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-3x mb-3" style="color: var(--blue);"></i>
                                    <?php if ($_SESSION['user_role'] === 'student'): ?>
                                        <p>View your official student information and update your contact details.</p>
                                    <?php else: ?>
                                        <p>Manage student information, import CSV data, and search records.</p>
                                    <?php endif; ?>
                                    <a href="data.php" class="btn btn-primary w-100">
                                        <?php echo ($_SESSION['user_role'] === 'student') ? 'View My Record' : 'Access Data Window'; ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('payment', $user_permissions)): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-credit-card me-2"></i>
                                        <?php echo (in_array($_SESSION['user_role'], ['supervisor', 'student'])) ? 'Payment History' : 'Payment Processing'; ?>
                                    </h5>
                                </div>
                                <div class="card-body text-center">
                                    <i class="fas fa-credit-card fa-3x mb-3" style="color: var(--orange-brown);"></i>
                                    <?php if ($_SESSION['user_role'] === 'student'): ?>
                                        <p>Check your dues balances and view your complete payment history.</p>
                                    <?php elseif ($_SESSION['user_role'] === 'supervisor'): ?>
                                        <p>View department payment history and track dues payments (view only).</p>
                                    <?php else: ?>
                                        <p>Process dues payments, generate receipts, and track payment history.</p>
                                    <?php endif; ?>
                                    <a href="payment.php" class="btn btn-primary w-100">
                                        <?php echo (in_array($_SESSION['user_role'], ['supervisor', 'student'])) ? 'View Payments' : 'Access Payment Window'; ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('report', $user_permissions)): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Reports</h5>
                                </div>
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-bar fa-3x mb-3" style="color: var(--yellow);"></i>
                                    <p>Generate reports, view analytics, and export data.</p>
                                    <a href="report.php" class="btn btn-primary w-100">Access Report Window</a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('users', $user_permissions)): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-user-cog me-2"></i>User Management</h5>
                                </div>
                                <div class="card-body text-center">
                                    <i class="fas fa-user-cog fa-3x mb-3" style="color: var(--blue);"></i>
                                    <p>Manage user accounts, roles, and system settings.</p>
                                    <a href="users.php" class="btn btn-primary w-100">Access User Management</a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>My Activity</h5>
                                </div>
                                <div class="card-body text-center">
                                    <i class="fas fa-history fa-3x mb-3" style="color: var(--blue);"></i>
                                    <p>View your personal history of actions and system interactions.</p>
                                    <a href="my_activity.php" class="btn btn-primary w-100">View My Activity</a>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (in_array('audit_logs', $user_permissions)): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Audit Logs</h5>
                                </div>
                                <div class="card-body text-center">
                                    <i class="fas fa-shield-alt fa-3x mb-3" style="color: var(--blue);"></i>
                                    <p>Track all system activities and user actions for security auditing.</p>
                                    <a href="audit_logs.php" class="btn btn-primary w-100">Access Audit Logs</a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('settings', $user_permissions)): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Dues & Sessions</h5>
                                </div>
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-check fa-3x mb-3" style="color: var(--orange-brown);"></i>
                                    <p>Manage academic years and define dues categories for the department.</p>
                                    <a href="settings.php" class="btn btn-primary w-100">Access Settings</a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="d-grid">
                                <a href="javascript:void(0)" class="btn btn-secondary" 
                                   onclick="confirmLogout('?action=logout')">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-grid">
                                <button class="btn btn-danger" onclick="confirmClose()">
                                    <i class="fas fa-times me-2"></i>Close Application
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <?php include '../includes/modals.php'; ?>
                    
                    <!-- Visual Analytics -->
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function confirmClose() {
            showConfirm({
                title: 'Close Application',
                message: 'Are you sure you want to close the application? You will be redirected to the login screen.',
                confirmBtnText: 'Close',
                confirmBtnClass: 'btn-danger',
                onConfirm: function() {
                    window.close();
                    // Fallback for browsers that don't allow window.close()
                    window.location.href = 'login.php';
                }
            });
        }
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($_SESSION['user_role'] !== 'student'): ?>
            // Revenue Trend Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($revenue_data, 'month')); ?>,
                    datasets: [{
                        label: 'Revenue (GHS)',
                        data: <?php echo json_encode(array_column($revenue_data, 'total')); ?>,
                        borderColor: '#FF8B00',
                        backgroundColor: 'rgba(255, 139, 0, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#050589',
                        pointRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            // Payment Completion Chart
            const completionCtx = document.getElementById('completionChart').getContext('2d');
            const paid = <?php echo floatval($total_revenue); ?>;
            const outstanding = Math.max(0, <?php echo floatval($total_expected - $total_revenue); ?>);
            
            new Chart(completionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Paid', 'Outstanding'],
                    datasets: [{
                        data: [paid, outstanding],
                        backgroundColor: ['#050589', '#F5D200'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    cutout: '70%',
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>

    <!-- Password Change Modal -->
    <?php if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password']): ?>
    <div class="modal fade" id="passwordChangeModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-shield-alt me-2"></i>Security Alert</h5>
                </div>
                <div class="modal-body">
                    <p>You are likely using a default system password. For your security, we recommend changing it now.</p>
                    <?php if (isset($error_message) && $error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <form method="POST" id="passwordChangeForm">
                        <input type="hidden" name="action" value="update_password_prompt">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <hr>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="dont_show_again" id="dont_show_again" value="1">
                            <label class="form-check-label" for="dont_show_again">
                                Don't show this again (Keep current password)
                            </label>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('passwordChangeModal'));
            myModal.show();
            
            const checkbox = document.getElementById('dont_show_again');
            const passFields = document.querySelectorAll('#passwordChangeForm input[type="password"]');
            const submitBtn = document.querySelector('#passwordChangeForm button[type="submit"]');
            
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    passFields.forEach(f => { f.disabled = true; f.required = false; });
                    submitBtn.textContent = "Confirm Preference";
                    submitBtn.classList.replace('btn-primary', 'btn-secondary');
                } else {
                    passFields.forEach(f => { f.disabled = false; f.required = true; });
                    submitBtn.textContent = "Update Password";
                    submitBtn.classList.replace('btn-secondary', 'btn-primary');
                }
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>