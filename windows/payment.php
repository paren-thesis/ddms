<?php
/**
 * HTU COMPSSA CODEFEST 2025 - Payment Window
 * Dues payment processing and history
 * 
 * Features:
 * - Process dues payments
 * - Generate receipts
 * - Track payment history
 * - Payment validation
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/email_helper.php';

// Check if user is logged in and has permission
if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'hod', 'cashier', 'supervisor', 'student'])) {
    redirect('login.php');
}

$error_message = '';
$success_message = '';
$payments = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'make_payment':
            // Only authorized roles can process payments
            if (in_array($_SESSION['user_role'], ['admin', 'hod', 'cashier'])) {
                handleMakePayment();
            } else {
                $error_message = 'You do not have permission to process payments.';
            }
            break;
    }
}

function handleMakePayment() {
    global $error_message, $success_message;
    
    $student_id = sanitizeInput($_POST['student_id'] ?? '');
    $due_id = sanitizeInput($_POST['due_id'] ?? '');
    $amount = sanitizeInput($_POST['amount'] ?? '');
    $payment_date = sanitizeInput($_POST['payment_date'] ?? date('Y-m-d'));
    $academic_year = sanitizeInput($_POST['academic_year'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? 'Dues Payment');
    $created_by = $_SESSION['user_id'] ?? null;
    
    if (empty($student_id) || empty($due_id) || empty($amount) || empty($payment_date) || empty($academic_year)) {
        $error_message = 'Please fill in all required fields.';
        return;
    }
    if (!is_numeric($amount) || $amount <= 0) {
        $error_message = 'Amount must be a positive number.';
        return;
    }
    
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Check if student exists
        $stmt = $pdo->prepare("SELECT s.*, p.programme_name FROM students s JOIN programmes p ON s.programme_id = p.programme_id WHERE s.student_id = ? AND s.deleted_at IS NULL");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
        if (!$student) {
            throw new Exception('Student not found.');
        }
        
        // Get due info
        $stmt = $pdo->prepare("SELECT * FROM dues WHERE due_id = ?");
        $stmt->execute([$due_id]);
        $due = $stmt->fetch();
        if (!$due) {
            throw new Exception('Due category not found.');
        }
        
        // Generate unique receipt number
        $receipt_no = generateReceiptNumber();
        
        // Insert payment (Main record)
        // total_amount is what they should pay (the due amount), amount_paid is what they are paying now
        // But the schema says total_amount, amount_paid, balance.
        // Balance = total_amount - amount_paid.
        $stmt = $pdo->prepare("INSERT INTO payments (receipt_no, student_id, academic_year, total_amount, amount_paid, balance, payment_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $balance = $due['amount'] - $amount;
        $stmt->execute([$receipt_no, $student_id, $academic_year, $due['amount'], $amount, $balance, $payment_date, $created_by]);
        $payment_id = $pdo->lastInsertId();
        
        // Insert payment item
        $stmt = $pdo->prepare("INSERT INTO payment_items (payment_id, due_id, amount, academic_year, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$payment_id, $due_id, $amount, $academic_year, $description]);
        
        $pdo->commit();
        
        logActivity('MAKE_PAYMENT', 'payments', $payment_id, null, [
            'student_id' => $student_id,
            'amount' => $amount,
            'receipt_no' => $receipt_no
        ]);
        
        // Send email receipt to student
        $email_note = '';
        $student_email = $student['email'] ?? '';
        // If student has no direct email, check via user account
        if (empty($student_email) && !empty($student['user_id'])) {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ?");
            $stmt->execute([$student['user_id']]);
            $student_email = $stmt->fetchColumn() ?: '';
        }
        
        if (!empty($student_email)) {
            $email_result = sendPaymentReceiptEmail($student_email, [
                'receipt_no' => $receipt_no,
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'index_no' => $student['index_no'],
                'due_name' => $due['due_name'],
                'amount_paid' => $amount,
                'total_due' => $due['amount'],
                'balance' => $balance,
                'payment_date' => $payment_date,
                'academic_year' => $academic_year
            ]);
            if ($email_result['success']) {
                $email_note = ' <span class="text-info"><i class="fas fa-envelope me-1"></i>Receipt emailed to ' . htmlspecialchars($student_email) . '</span>';
            } else {
                $email_note = ' <span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Email not sent: ' . htmlspecialchars($email_result['message']) . '</span>';
            }
        }
        
        $success_message = 'Payment processed successfully! Receipt No: ' . $receipt_no . 
                           ' <a href="generate_receipt.php?id='.$payment_id.'" target="_blank" class="btn btn-sm btn-success ms-3"><i class="fas fa-print me-1"></i>Print Receipt</a>' . $email_note;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_message = 'Failed to process payment: ' . $e->getMessage();
    }
}

// Get dues for dropdown
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT due_id, due_name, amount, academic_year FROM dues WHERE status = 'Active' ORDER BY academic_year DESC, due_name");
    $stmt->execute();
    $dues = $stmt->fetchAll();
} catch (PDOException $e) {
    $dues = [];
}

// Get students for dropdown
try {
    $stmt = $pdo->prepare("SELECT student_id, index_no, first_name, last_name FROM students WHERE deleted_at IS NULL ORDER BY first_name, last_name");
    $stmt->execute();
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $students = [];
}

// Get unique academic years for filter
try {
    $stmt = $pdo->query("SELECT DISTINCT session_name FROM academic_sessions ORDER BY session_name DESC");
    $academic_years = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $academic_years = [];
}

// Get search and filter parameters
$search = sanitizeInput($_GET['search'] ?? '');
$year_filter = sanitizeInput($_GET['year'] ?? '');

// Pagination logic
$records_per_page = 15;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $records_per_page;

// Get payment history with filters
try {
    $params = [];
    $where_conditions = ["1=1"];
    
    if ($_SESSION['user_role'] === 'student') {
        $where_conditions[] = "s.user_id = ?";
        $params[] = $_SESSION['user_id'];
    }

    if (!empty($search)) {
        $where_conditions[] = "(s.index_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR p.receipt_no LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    if (!empty($year_filter)) {
        $where_conditions[] = "p.academic_year = ?";
        $params[] = $year_filter;
    }

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(DISTINCT p.payment_id) 
                  FROM payments p
                  LEFT JOIN students s ON p.student_id = s.student_id
                  $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);

    $sql = "SELECT p.*, s.index_no, s.first_name, s.last_name, u.username AS lecturer, d.due_name
            FROM payments p
            LEFT JOIN students s ON p.student_id = s.student_id
            LEFT JOIN users u ON p.created_by = u.user_id
            LEFT JOIN payment_items pi ON p.payment_id = pi.payment_id
            LEFT JOIN dues d ON pi.due_id = d.due_id
            $where_clause
            ORDER BY p.payment_id DESC
            LIMIT $records_per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
} catch (PDOException $e) {
    $payments = [];
    $total_records = 0;
    $total_pages = 0;
}

// Fetch session-based balance summary for students
$balance_summary = [];
if ($_SESSION['user_role'] === 'student') {
    try {
        $sql = "SELECT d.academic_year, SUM(d.amount) as total_dues 
                FROM dues d
                JOIN students s ON (d.programme_id = s.programme_id OR d.programme_id IS NULL)
                WHERE s.user_id = ? AND d.is_mandatory = 1 AND d.status = 'Active'
                GROUP BY d.academic_year";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        $dues_by_year = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $sql = "SELECT academic_year, SUM(amount_paid) as total_paid
                FROM payments p
                JOIN students s ON p.student_id = s.student_id
                WHERE s.user_id = ?
                GROUP BY academic_year";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        $paid_by_year = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($dues_by_year as $year => $total_due) {
            $paid = $paid_by_year[$year] ?? 0;
            $balance_summary[] = [
                'year' => $year,
                'total_due' => $total_due,
                'total_paid' => $paid,
                'balance' => $total_due - $paid
            ];
        }
    } catch (PDOException $e) {
        // Silently fail
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Payment Processing</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Header with Logo and Title -->
    <header class="app-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-2">
                    <img src="../assets/compssa_logo.png" alt="HTU Logo" class="logo">
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
                        <?php echo $_SESSION['user_role'] === 'student' ? 'Payment History' : 'Payment Processing'; ?>
                    </h2>
                    
                    <!-- Display Messages -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    
                    <!-- Payment Form - Only for Admin, HOD, and Cashier -->
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'hod', 'cashier'])): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Process Payment</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="make_payment">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="student_search" class="form-label">Find Student</label>
                                            <input type="text" class="form-control" id="student_search" list="student_list" placeholder="Index or Name..." autocomplete="off">
                                            <datalist id="student_list">
                                                <?php foreach ($students as $student): ?>
                                                    <option data-id="<?php echo $student['student_id']; ?>" 
                                                            value="<?php echo $student['index_no'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']; ?>">
                                                <?php endforeach; ?>
                                            </datalist>
                                            <input type="hidden" name="student_id" id="student_id" required>
                                            <small id="selection_feedback" class="d-block mt-1"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="due_id" class="form-label">Due Category</label>
                                            <select class="form-control" id="due_id" name="due_id" required>
                                                <option value="">Select Category</option>
                                                <?php foreach ($dues as $due): ?>
                                                    <option value="<?php echo $due['due_id']; ?>" data-amount="<?php echo $due['amount']; ?>">
                                                        <?php echo $due['due_name'] . ' (' . $due['academic_year'] . ') - GH₵' . $due['amount']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label for="amount" class="form-label">Paying Now</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label for="payment_date" class="form-label">Date</label>
                                            <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label for="academic_year" class="form-label">For Year</label>
                                            <input type="text" class="form-control" id="academic_year" name="academic_year" value="<?php echo getCurrentAcademicYear(); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-10">
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description / Remarks</label>
                                            <input type="text" class="form-control" id="description" name="description" placeholder="Optional notes...">
                                        </div>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <div class="d-grid w-100 mb-3">
                                            <button type="button" class="btn btn-primary" id="previewPaymentBtn">
                                                <i class="fas fa-eye me-2"></i>Review & Pay
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Student/Supervisor Personal Summary -->
                    <?php if ($_SESSION['user_role'] === 'student'): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-wallet me-2"></i>My Outstanding Balances</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php if (empty($balance_summary)): ?>
                                            <div class="col-12 text-center text-muted">No mandatory dues records found.</div>
                                        <?php else: ?>
                                            <?php foreach ($balance_summary as $summary): ?>
                                                <div class="col-md-4 mb-3">
                                                    <div class="p-3 border rounded text-center" style="background: #f8f9fa;">
                                                        <h6 class="text-primary"><?php echo $summary['year']; ?></h6>
                                                        <div class="mb-1"><strong>Dues:</strong> <?php echo formatCurrency($summary['total_due']); ?></div>
                                                        <div class="mb-1 text-success"><strong>Paid:</strong> <?php echo formatCurrency($summary['total_paid']); ?></div>
                                                        <div class="h5 mt-2 <?php echo $summary['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                            <strong>Balance:</strong> <?php echo formatCurrency($summary['balance']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Supervisor View Only Message -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-eye me-2"></i>Payment History (View Only)</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Supervisor Access:</strong> You can view payment history but cannot process new payments. 
                                Only administrators and cashiers can process payments.
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Payment History Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Payments</h5>
                        </div>
                        <div class="card-body">
                            <!-- Search & Filter Bar -->
                            <form method="GET" class="mb-4">
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0">
                                                <i class="fas fa-search text-muted"></i>
                                            </span>
                                            <input type="text" name="search" class="form-control border-start-0" 
                                                   placeholder="Search Index No, Name, or Receipt..." 
                                                   value="<?php echo sanitizeInput($search); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0">
                                                <i class="fas fa-calendar-alt text-muted"></i>
                                            </span>
                                            <select name="year" class="form-select border-start-0">
                                                <option value="">All Academic Years</option>
                                                <?php foreach ($academic_years as $year): ?>
                                                    <option value="<?php echo $year; ?>" <?php echo ($year_filter === $year) ? 'selected' : ''; ?>>
                                                        <?php echo $year; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-filter me-1"></i> Filter
                                            </button>
                                            <?php if (!empty($search) || !empty($year_filter)): ?>
                                                <a href="payment.php" class="btn btn-outline-secondary">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Receipt No</th>
                                            <th>Student</th>
                                            <th>Category</th>
                                            <th>Paid (GHC)</th>
                                            <th>Balance (GHC)</th>
                                            <th>Date</th>
                                            <th>Year</th>
                                            <th>User</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($payments)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center">No payments found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($payments as $payment): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-dark"><?php echo sanitizeInput($payment['receipt_no']); ?></span>
                                                    </td>
                                                    <td><?php echo sanitizeInput($payment['index_no'] . ' - ' . $payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                                    <td><?php echo sanitizeInput($payment['due_name'] ?? 'N/A'); ?></td>
                                                    <td class="fw-bold text-success"><?php echo formatCurrency($payment['amount_paid']); ?></td>
                                                    <td class="text-danger"><?php echo formatCurrency($payment['balance']); ?></td>
                                                    <td><?php echo date('d M, Y', strtotime($payment['payment_date'])); ?></td>
                                                    <td><?php echo sanitizeInput($payment['academic_year']); ?></td>
                                                    <td><?php echo sanitizeInput($payment['lecturer']); ?></td>
                                                    <td>
                                                        <?php if ($_SESSION['user_role'] !== 'student'): ?>
                                                        <a href="generate_receipt.php?id=<?php echo $payment['payment_id']; ?>" 
                                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-print"></i>
                                                        </a>
                                                        <?php else: ?>
                                                        <span class="text-muted">-</span>
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

    <!-- Payment Preview Modal -->
    <?php if (in_array($_SESSION['user_role'], ['admin', 'hod', 'cashier'])): ?>
    <div class="modal fade" id="paymentPreviewModal" tabindex="-1" aria-labelledby="paymentPreviewLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #050589;">
                    <h5 class="modal-title text-white" id="paymentPreviewLabel">
                        <i class="fas fa-receipt me-2"></i>Confirm Payment Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Please review the payment details below before proceeding.</p>
                    <table class="table table-bordered mb-0">
                        <tbody>
                            <tr>
                                <td class="fw-bold text-muted" style="width:40%">Student</td>
                                <td id="preview_student" class="fw-bold"></td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-muted">Due Category</td>
                                <td id="preview_due"></td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-muted">Total Due</td>
                                <td id="preview_total_due"></td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-muted">Paying Now</td>
                                <td id="preview_amount" class="fw-bold text-success fs-5"></td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-muted">Balance After</td>
                                <td id="preview_balance" class="fw-bold"></td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-muted">Payment Date</td>
                                <td id="preview_date"></td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-muted">Academic Year</td>
                                <td id="preview_year"></td>
                            </tr>
                            <tr id="preview_desc_row">
                                <td class="fw-bold text-muted">Description</td>
                                <td id="preview_description"></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="alert alert-info mt-3 mb-0 py-2">
                        <i class="fas fa-envelope me-1"></i> A receipt will be emailed to the student upon confirmation.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-pencil-alt me-1"></i>Edit
                    </button>
                    <button type="button" class="btn btn-primary" id="confirmPaymentBtn">
                        <i class="fas fa-check-circle me-2"></i>Confirm & Process
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle searchable student selection
        const studentSearchEl = document.getElementById('student_search');
        if (studentSearchEl) {
            studentSearchEl.addEventListener('input', function(e) {
                const input = e.target;
                const list = document.getElementById('student_list');
                const hiddenInput = document.getElementById('student_id');
                const options = list.options;
                const feedback = document.getElementById('selection_feedback');
                
                hiddenInput.value = ''; // Reset hidden ID
                if (feedback) feedback.textContent = '';
                
                for (let i = 0; i < options.length; i++) {
                    if (options[i].value === input.value) {
                        hiddenInput.value = options[i].getAttribute('data-id');
                        if (feedback) {
                            feedback.textContent = '✓ Student Selected';
                            feedback.style.color = 'green';
                        }
                        break;
                    }
                }
            });

            // Ensure hidden input is cleared if search is cleared
            studentSearchEl.addEventListener('change', function(e) {
                const hiddenInput = document.getElementById('student_id');
                const feedback = document.getElementById('selection_feedback');
                if (e.target.value === '') {
                    hiddenInput.value = '';
                    if (feedback) feedback.textContent = '';
                }
            });
        }

        // Auto-fill amount when due is selected
        const dueSelectEl = document.getElementById('due_id');
        if (dueSelectEl) {
            dueSelectEl.addEventListener('change', function(e) {
                const selectedOption = e.target.options[e.target.selectedIndex];
                const amountInput = document.getElementById('amount');
                if (selectedOption && selectedOption.dataset.amount) {
                    amountInput.value = selectedOption.dataset.amount;
                }
            });
        }

        // Payment Preview Modal Logic
        const previewBtn = document.getElementById('previewPaymentBtn');
        if (previewBtn) {
            previewBtn.addEventListener('click', function() {
                const form = this.closest('form');
                const studentId = document.getElementById('student_id').value;
                const studentText = document.getElementById('student_search').value;
                const dueSelect = document.getElementById('due_id');
                const dueOption = dueSelect.options[dueSelect.selectedIndex];
                const amount = parseFloat(document.getElementById('amount').value) || 0;
                const paymentDate = document.getElementById('payment_date').value;
                const academicYear = document.getElementById('academic_year').value;
                const description = document.getElementById('description').value;

                // Validate form first
                if (!studentId) {
                    alert('Please select a student.');
                    document.getElementById('student_search').focus();
                    return;
                }
                if (!dueOption || !dueOption.value) {
                    alert('Please select a due category.');
                    dueSelect.focus();
                    return;
                }
                if (amount <= 0) {
                    alert('Please enter a valid payment amount.');
                    document.getElementById('amount').focus();
                    return;
                }
                if (!paymentDate) {
                    alert('Please enter a payment date.');
                    return;
                }
                if (!academicYear) {
                    alert('Please enter the academic year.');
                    return;
                }

                // Populate preview modal
                const totalDue = parseFloat(dueOption.dataset.amount) || 0;
                const balance = totalDue - amount;

                document.getElementById('preview_student').textContent = studentText;
                document.getElementById('preview_due').textContent = dueOption.textContent.trim();
                document.getElementById('preview_total_due').textContent = 'GH₵ ' + totalDue.toFixed(2);
                document.getElementById('preview_amount').textContent = 'GH₵ ' + amount.toFixed(2);
                
                const balanceEl = document.getElementById('preview_balance');
                balanceEl.textContent = 'GH₵ ' + balance.toFixed(2);
                balanceEl.className = balance > 0 ? 'fw-bold text-danger' : 'fw-bold text-success';
                
                document.getElementById('preview_date').textContent = paymentDate;
                document.getElementById('preview_year').textContent = academicYear;
                
                const descRow = document.getElementById('preview_desc_row');
                if (description) {
                    document.getElementById('preview_description').textContent = description;
                    descRow.style.display = '';
                } else {
                    descRow.style.display = 'none';
                }

                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('paymentPreviewModal'));
                modal.show();
            });

            // Confirm button submits the form
            document.getElementById('confirmPaymentBtn').addEventListener('click', function() {
                const form = document.querySelector('form[action=""] input[name="action"][value="make_payment"]')?.closest('form') 
                             || document.querySelector('form');
                form.submit();
            });
        }
    </script>
</body>
</html>