<?php
/**
 * HTU COMPSSA DDMS - Academic Progression Application
 * Handle Batch Promotion and Returning Top-Up Students
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and has progression access
if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'hod'])) {
    redirect('control.php');
}

$error_message = '';
$success_message = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'run_promotion':
            handleBatchPromotion();
            break;
        case 'return_top_up':
            handleReturnTopUp();
            break;
    }
}

function handleBatchPromotion() {
    global $error_message, $success_message;
    
    // Safety check: Ensure only Admin can run this
    if ($_SESSION['user_role'] !== 'admin') {
        $error_message = 'Unauthorized action. Only Administrators can run batch promotions.';
        return;
    }

    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        $stats = [
            'graduated' => 0,
            'promoted_to_400' => 0,
            'promoted_to_300' => 0,
            'promoted_to_200' => 0
        ];

        // 1. Graduate 400 & Top-Up (Status -> Inactive, is_graduated -> 1)
        $stmtGrad = $pdo->prepare("UPDATE students SET status = 'Inactive', is_graduated = 1 WHERE status = 'Active' AND programme_level IN ('400', 'Top-Up')");
        $stmtGrad->execute();
        $stats['graduated'] = $stmtGrad->rowCount();

        // 2. Promote 300 to 400
        $stmt400 = $pdo->prepare("UPDATE students SET programme_level = '400' WHERE status = 'Active' AND programme_level = '300'");
        $stmt400->execute();
        $stats['promoted_to_400'] = $stmt400->rowCount();

        // 3. Promote 200 to 300
        $stmt300 = $pdo->prepare("UPDATE students SET programme_level = '300' WHERE status = 'Active' AND programme_level = '200'");
        $stmt300->execute();
        $stats['promoted_to_300'] = $stmt300->rowCount();

        // 4. Promote 100 to 200
        $stmt200 = $pdo->prepare("UPDATE students SET programme_level = '200' WHERE status = 'Active' AND programme_level = '100'");
        $stmt200->execute();
        $stats['promoted_to_200'] = $stmt200->rowCount();

        $pdo->commit();

        $total_promoted = $stats['promoted_to_400'] + $stats['promoted_to_300'] + $stats['promoted_to_200'];
        logActivity('BATCH_PROMOTION', 'students', 0, null, $stats);

        $success_message = "Academic Progression Complete! Promoted {$total_promoted} students, graduated {$stats['graduated']} students. Check Audit Logs for details.";
        
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $error_message = 'Batch Promotion failed: ' . $e->getMessage();
    }
}

function handleReturnTopUp() {
    global $error_message, $success_message;
    $student_id = (int)($_POST['student_id'] ?? 0);
    
    if (!$student_id) {
        $error_message = "Please select a student to reactivate.";
        return;
    }
    
    try {
        $pdo = getDBConnection();
        
        // Check if student is indeed graduated
        $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
        
        if (!$student) {
            $error_message = "Student not found.";
            return;
        }
        
        if ($student['is_graduated'] == 0 && $student['status'] == 'Active') {
            $error_message = "This student is already active and not graduated.";
            return;
        }
        
        // Reactivate for Top-Up
        $stmt = $pdo->prepare("UPDATE students SET status = 'Active', is_graduated = 0, programme_level = 'Top-Up' WHERE student_id = ?");
        $stmt->execute([$student_id]);
        
        logActivity('STUDENT_RETURN_TOPUP', 'students', $student_id, null, [
            'index_no' => $student['index_no'],
            'previous_level' => $student['programme_level']
        ]);
        
        $success_message = "Student {$student['first_name']} {$student['last_name']} ({$student['index_no']}) has been successfully reactivated for Top-Up.";
        
    } catch (PDOException $e) {
        $error_message = "Failed to reactivate student: " . $e->getMessage();
    }
}

// Fetch graduated students for Top-Up return list
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT student_id, index_no, first_name, last_name FROM students WHERE is_graduated = 1 ORDER BY first_name, last_name");
    $stmt->execute();
    $graduated_students = $stmt->fetchAll();
} catch (PDOException $e) {
    $graduated_students = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Academic Progression</title>
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
                        Academic Progression Management
                    </h2>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger shadow-sm"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <?php if ($success_message): ?>
                        <div class="alert alert-success shadow-sm"><?php echo $success_message; ?></div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Column 1: Batch Promotion -->
                        <div class="col-md-6">
                            <div class="card mb-4 border-danger shadow-sm h-100">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>End of Year Promotion</h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted">Use this tool to advance students to the next academic level at the end of the year.</p>
                                    
                                    <div class="alert alert-warning border-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>WARNING: Irreversible Action.</strong> 
                                        <ul class="mb-0 mt-2">
                                            <li>Advances levels: 100&rarr;200, 200&rarr;300, 300&rarr;400.</li>
                                            <li>Graduates 400 and Top-Up students.</li>
                                            <li>Does NOT affect <strong>Inactive</strong> (deferred) students.</li>
                                        </ul>
                                    </div>
                                    
                                    <form method="POST" id="promotionForm" class="mt-4">
                                        <input type="hidden" name="action" value="run_promotion">
                                        <button type="button" class="btn btn-danger btn-lg w-100 fw-bold py-3" onclick="confirmPromotion()">
                                            <i class="fas fa-forward me-2"></i>Run Batch Promotion
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Column 2: Return for Top-Up -->
                        <div class="col-md-6">
                            <div class="card mb-4 border-primary shadow-sm h-100">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Return for Top-Up</h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted">Reactivate graduated HND students who are returning for their Top-Up degree.</p>
                                    
                                    <div class="alert alert-info border-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Reactivating a student will:
                                        <ul class="mb-0 mt-2">
                                            <li>Set their status back to <strong>Active</strong>.</li>
                                            <li>Set their level to <strong>Top-Up</strong>.</li>
                                            <li>Allow them to appear in payment processing list.</li>
                                        </ul>
                                    </div>

                                    <form method="POST" class="mt-4">
                                        <input type="hidden" name="action" value="return_top_up">
                                        <div class="mb-3">
                                            <label for="student_search" class="form-label fw-bold">Find Graduated Student</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                <input type="text" class="form-control form-control-lg" id="student_search" list="graduated_list" placeholder="Search by Index or Name..." autocomplete="off">
                                            </div>
                                            <datalist id="graduated_list">
                                                <?php foreach ($graduated_students as $student): ?>
                                                    <option data-id="<?php echo $student['student_id']; ?>" 
                                                            value="<?php echo $student['index_no'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']; ?>">
                                                <?php endforeach; ?>
                                            </datalist>
                                            <input type="hidden" name="student_id" id="student_id">
                                            <small id="selection_feedback" class="d-block mt-2 fw-bold"></small>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold py-3" id="reactivateBtn" disabled>
                                            <i class="fas fa-undo me-2"></i>Reactivate for Top-Up
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Custom Prompt Include -->
    <?php include '../includes/modals.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle searchable student selection
        const studentSearchEl = document.getElementById('student_search');
        if (studentSearchEl) {
            studentSearchEl.addEventListener('input', function(e) {
                const input = e.target;
                const list = document.getElementById('graduated_list');
                const hiddenInput = document.getElementById('student_id');
                const options = list.options;
                const feedback = document.getElementById('selection_feedback');
                const btn = document.getElementById('reactivateBtn');
                
                hiddenInput.value = ''; // Reset hidden ID
                btn.disabled = true;
                if (feedback) feedback.textContent = '';
                
                for (let i = 0; i < options.length; i++) {
                    if (options[i].value === input.value) {
                        hiddenInput.value = options[i].getAttribute('data-id');
                        btn.disabled = false;
                        if (feedback) {
                            feedback.textContent = '✓ Student Found: ' + input.value;
                            feedback.style.color = 'green';
                        }
                        break;
                    }
                }
            });
        }

        function confirmPromotion() {
            if (typeof showConfirm === 'function') {
                showConfirm({
                    title: 'Confirm Academic Progression',
                    message: 'Are you absolutely sure you want to run the Batch Promotion? This will advance all active students by one level and graduate final-year students. This action cannot be easily undone.',
                    confirmBtnText: 'Yes, Run Promotion',
                    confirmBtnClass: 'btn-danger',
                    onConfirm: function() {
                        document.getElementById('promotionForm').submit();
                    }
                });
            } else {
                if (confirm('Are you absolutely sure you want to run the Batch Promotion? This action is irreversible.')) {
                    document.getElementById('promotionForm').submit();
                }
            }
        }
    </script>
</body>
</html>
