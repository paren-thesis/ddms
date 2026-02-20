<?php
/**
 * HTU COMPSSA DDMS - Settings Window
 * Manage Academic Sessions and Dues Categories
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and has settings access
if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'hod', 'cashier'])) {
    redirect('control.php');
}

$error_message = '';
$success_message = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_session':
            handleAddSession();
            break;
        case 'set_current_session':
            handleSetCurrentSession();
            break;
        case 'add_due':
            handleAddDue();
            break;
        case 'edit_due':
            handleEditDue();
            break;
        case 'delete_due':
            handleDeleteDue();
            break;
    }
}

function handleAddSession() {
    global $error_message, $success_message;
    $session_name = sanitizeInput($_POST['session_name'] ?? '');
    
    if (empty($session_name)) {
        $error_message = 'Session name is required.';
        return;
    }
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO academic_sessions (session_name) VALUES (?)");
        $stmt->execute([$session_name]);
        
        logActivity('ADD_SESSION', 'academic_sessions', $pdo->lastInsertId(), null, ['session_name' => $session_name]);
        $success_message = 'Academic session added successfully.';
    } catch (PDOException $e) {
        $error_message = 'Failed to add session: ' . $e->getMessage();
    }
}

function handleSetCurrentSession() {
    global $error_message, $success_message;
    $session_id = (int)($_POST['session_id'] ?? 0);
    
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Reset all
        $pdo->query("UPDATE academic_sessions SET is_current = 0");
        
        // Set new current
        $stmt = $pdo->prepare("UPDATE academic_sessions SET is_current = 1 WHERE session_id = ?");
        $stmt->execute([$session_id]);
        
        $pdo->commit();
        logActivity('SET_CURRENT_SESSION', 'academic_sessions', $session_id);
        $success_message = 'Current session updated successfully.';
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_message = 'Failed to update current session: ' . $e->getMessage();
    }
}

function handleAddDue() {
    global $error_message, $success_message;
    $due_name = sanitizeInput($_POST['due_name'] ?? '');
    $due_code = sanitizeInput($_POST['due_code'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $academic_year = sanitizeInput($_POST['academic_year'] ?? '');
    $programme_id = $_POST['programme_id'] ? (int)$_POST['programme_id'] : null;
    
    if (empty($due_name) || empty($due_code) || $amount <= 0 || empty($academic_year)) {
        $error_message = 'Please fill in all required fields.';
        return;
    }
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO dues (due_name, due_code, amount, academic_year, programme_id, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$due_name, $due_code, $amount, $academic_year, $programme_id, $_SESSION['user_id']]);
        
        logActivity('ADD_DUE', 'dues', $pdo->lastInsertId(), null, ['due_code' => $due_code]);
        $success_message = 'Due category added successfully.';
    } catch (PDOException $e) {
        $error_message = 'Failed to add due: ' . $e->getMessage();
    }
}

function handleEditDue() {
    global $error_message, $success_message;
    $due_id = (int)($_POST['due_id'] ?? 0);
    $due_name = sanitizeInput($_POST['due_name'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? 'Active');
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE dues SET due_name = ?, amount = ?, status = ? WHERE due_id = ?");
        $stmt->execute([$due_name, $amount, $status, $due_id]);
        
        logActivity('EDIT_DUE', 'dues', $due_id);
        $success_message = 'Due category updated successfully.';
    } catch (PDOException $e) {
        $error_message = 'Failed to update due: ' . $e->getMessage();
    }
}

function handleDeleteDue() {
    global $error_message, $success_message;
    $due_id = (int)($_POST['due_id'] ?? 0);
    
    try {
        $pdo = getDBConnection();
        // Check if there are payments linked
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payment_items WHERE due_id = ?");
        $stmt->execute([$due_id]);
        if ($stmt->fetchColumn() > 0) {
            $error_message = 'Cannot delete due category with existing payments. You should deactivate it instead.';
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM dues WHERE due_id = ?");
        $stmt->execute([$due_id]);
        
        logActivity('DELETE_DUE', 'dues', $due_id);
        $success_message = 'Due category deleted successfully.';
    } catch (PDOException $e) {
        $error_message = 'Failed to delete due: ' . $e->getMessage();
    }
}

// Fetch Data
try {
    $pdo = getDBConnection();
    
    // Sessions
    $sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY session_name DESC")->fetchAll();
    
    // Dues
    $dues = $pdo->query("SELECT d.*, p.programme_name 
                         FROM dues d 
                         LEFT JOIN programmes p ON d.programme_id = p.programme_id 
                         ORDER BY d.academic_year DESC, d.due_name")->fetchAll();
                         
    // Programmes for dropdown
    $programmes = $pdo->query("SELECT programme_id, programme_name FROM programmes WHERE status = 'Active'")->fetchAll();
    
} catch (PDOException $e) {
    $error_message = 'Data fetching failed: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Settings</title>
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
                        System Settings
                    </h2>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Sessions Management -->
                        <div class="col-md-5">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Academic Sessions</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" class="mb-3">
                                        <input type="hidden" name="action" value="add_session">
                                        <div class="input-group">
                                            <input type="text" name="session_name" class="form-control" placeholder="e.g. 2025/2026" required>
                                            <button type="submit" class="btn btn-primary">Add Session</button>
                                        </div>
                                    </form>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Session</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($sessions as $session): ?>
                                                    <tr>
                                                        <td><?php echo $session['session_name']; ?></td>
                                                        <td>
                                                            <?php if ($session['is_current']): ?>
                                                                <span class="badge bg-success">Current</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if (!$session['is_current']): ?>
                                                                <form method="POST">
                                                                    <input type="hidden" name="action" value="set_current_session">
                                                                    <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                                                    <button type="submit" class="btn btn-xs btn-outline-primary">Set Current</button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dues Management -->
                        <div class="col-md-7">
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Dues Categories</h5>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDueModal">
                                        <i class="fas fa-plus me-1"></i>New Category
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Year</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dues as $due): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="fw-bold"><?php echo $due['due_name']; ?></div>
                                                            <small class="text-muted"><?php echo $due['due_code']; ?></small>
                                                        </td>
                                                        <td><?php echo $due['academic_year']; ?></td>
                                                        <td><?php echo formatCurrency($due['amount']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $due['status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                                                <?php echo $due['status']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-xs btn-outline-primary" onclick='editDue(<?php echo json_encode($due); ?>)'>
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-xs btn-outline-danger" onclick='deleteDue(<?php echo $due["due_id"]; ?>)'>
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Due Modal -->
    <div class="modal fade" id="addDueModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Due Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_due">
                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" name="due_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Code * (Unique ID)</label>
                            <input type="text" name="due_code" class="form-control" placeholder="e.g. COMPSSA-2025/2026" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount (GHS) *</label>
                                <input type="number" step="0.01" name="amount" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Academic Year *</label>
                                <select name="academic_year" class="form-select">
                                    <?php foreach ($sessions as $s): ?>
                                        <option value="<?php echo $s['session_name']; ?>" <?php echo $s['is_current'] ? 'selected' : ''; ?>>
                                            <?php echo $s['session_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Programme (Optional)</label>
                            <select name="programme_id" class="form-select">
                                <option value="">All Programmes</option>
                                <?php foreach ($programmes as $p): ?>
                                    <option value="<?php echo $p['programme_id']; ?>"><?php echo $p['programme_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Due</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Due Modal -->
    <div class="modal fade" id="editDueModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Due Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_due">
                        <input type="hidden" name="due_id" id="edit_due_id">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="due_name" id="edit_due_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount (GHS)</label>
                            <input type="number" step="0.01" name="amount" id="edit_due_amount" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_due_status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Due</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Due Form (hidden) -->
    <form id="deleteDueForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete_due">
        <input type="hidden" name="due_id" id="delete_due_id">
    </form>

    <?php include '../includes/modals.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const editModal = new bootstrap.Modal(document.getElementById('editDueModal'));
        function editDue(due) {
            document.getElementById('edit_due_id').value = due.due_id;
            document.getElementById('edit_due_name').value = due.due_name;
            document.getElementById('edit_due_amount').value = due.amount;
            document.getElementById('edit_due_status').value = due.status;
            editModal.show();
        }

        function deleteDue(id) {
            showConfirm({
                title: 'Delete Category',
                message: 'Are you sure you want to delete this due category?',
                confirmBtnText: 'Delete',
                confirmBtnClass: 'btn-danger',
                onConfirm: function() {
                    document.getElementById('delete_due_id').value = id;
                    document.getElementById('deleteDueForm').submit();
                }
            });
        }

        function deleteSession(id) {
            // Implementation for session deletion
            alert('Cannot delete sessions. You can only mark them as inactive.');
        }
    </script>
</body>
</html>
