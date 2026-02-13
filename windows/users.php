<?php
/**
 * HTU COMPSSA CODEFEST 2025 - User Management Window
 * Admin-only user management system
 * 
 * Features:
 * - View all users
 * - Add new users
 * - Edit existing users
 * - Delete users
 * - Role management
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    redirect('login.php');
}

$error_message = '';
$success_message = '';
$users = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_user':
                handleAddUser();
                break;
            case 'edit_user':
                handleEditUser();
                break;
            case 'delete_user':
                handleDeleteUser();
                break;
        }
    }
}

function handleAddUser() {
    global $error_message, $success_message;
    
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'];
    $role = sanitizeInput($_POST['role']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error_message = 'Username, email, password and role are required.';
        return;
    }
    
    if (!isValidEmail($email)) {
        $error_message = 'Please enter a valid email address.';
        return;
    }
    
    if (!isValidName($first_name) || !isValidName($last_name)) {
        $error_message = 'Names should only contain letters, spaces, hyphens, or apostrophes.';
        return;
    }
    
    if (!empty($phone) && !isValidPhone($phone)) {
        $error_message = 'Invalid phone number format. Please use 10-15 digits.';
        return;
    }
    
    if (strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
        return;
    }
    
    try {
        $pdo = getDBConnection();
        
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND deleted_at IS NULL");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error_message = 'Username or email already exists.';
            return;
        }
        
        // Get role_id
        $stmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = ?");
        $stmt->execute([$role]);
        $roleData = $stmt->fetch();
        if (!$roleData) {
            $error_message = 'Invalid role selected.';
            return;
        }
        
        // Insert new user
        $hashedPassword = hashPassword($password);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hashedPassword, $first_name, $last_name, $phone, $roleData['role_id']]);
        
        $new_user_id = $pdo->lastInsertId();
        logActivity('ADD_USER', 'users', $new_user_id, null, ['username' => $username, 'role' => $role]);
        
        $success_message = 'User added successfully.';
    } catch (PDOException $e) {
        $error_message = 'Failed to add user: ' . $e->getMessage();
    }
}

function handleEditUser() {
    global $error_message, $success_message;
    
    $user_id = (int)$_POST['user_id'];
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $role = sanitizeInput($_POST['role']);
    $password = $_POST['password'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $is_locked = isset($_POST['is_locked']) ? 1 : 0;
    
    // Validation
    if (empty($username) || empty($email) || empty($role)) {
        $error_message = 'Username, email, and role are required.';
        return;
    }
    
    if (!isValidEmail($email)) {
        $error_message = 'Please enter a valid email address.';
        return;
    }
    
    if (!isValidName($first_name) || !isValidName($last_name)) {
        $error_message = 'Names should only contain letters, spaces, hyphens, or apostrophes.';
        return;
    }
    
    if (!empty($phone) && !isValidPhone($phone)) {
        $error_message = 'Invalid phone number format. Please use 10-15 digits.';
        return;
    }
    
    try {
        $pdo = getDBConnection();
        
        // Check if username or email already exists (excluding current user)
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ? AND deleted_at IS NULL");
        $stmt->execute([$username, $email, $user_id]);
        if ($stmt->fetch()) {
            $error_message = 'Username or email already exists.';
            return;
        }
        
        // Get role_id
        $stmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = ?");
        $stmt->execute([$role]);
        $roleData = $stmt->fetch();
        if (!$roleData) {
            $error_message = 'Invalid role selected.';
            return;
        }
        
        // Update user
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $error_message = 'Password must be at least 8 characters long.';
                return;
            }
            $hashedPassword = hashPassword($password);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, phone = ?, password_hash = ?, role_id = ?, is_active = ?, is_locked = ? WHERE user_id = ?");
            $stmt->execute([$username, $email, $first_name, $last_name, $phone, $hashedPassword, $roleData['role_id'], $is_active, $is_locked, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, phone = ?, role_id = ?, is_active = ?, is_locked = ? WHERE user_id = ?");
            $stmt->execute([$username, $email, $first_name, $last_name, $phone, $roleData['role_id'], $is_active, $is_locked, $user_id]);
        }
        
        logActivity('EDIT_USER', 'users', $user_id, null, ['username' => $username]);
        
        $success_message = 'User updated successfully.';
    } catch (PDOException $e) {
        $error_message = 'Failed to update user: ' . $e->getMessage();
    }
}

function handleDeleteUser() {
    global $error_message, $success_message;
    
    $user_id = (int)$_POST['user_id'];
    
    // Prevent admin from deleting themselves
    if ($user_id == $_SESSION['user_id']) {
        $error_message = 'You cannot delete your own account.';
        return;
    }
    
    try {
        $pdo = getDBConnection();
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ? AND deleted_at IS NULL");
        $stmt->execute([$user_id]);
        if (!$stmt->fetch()) {
            $error_message = 'User not found.';
            return;
        }
        
        // Soft delete user
        $stmt = $pdo->prepare("UPDATE users SET deleted_at = CURRENT_TIMESTAMP WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        logActivity('DELETE_USER', 'users', $user_id);
        
        $success_message = 'User deleted (archived) successfully.';
    } catch (PDOException $e) {
        $error_message = 'Failed to delete user: ' . $e->getMessage();
    }
}

// Handle Password Reset Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_reset') {
    $request_id = sanitizeInput($_POST['request_id'] ?? '');
    $user_id = sanitizeInput($_POST['reset_user_id'] ?? '');
    $email = sanitizeInput($_POST['reset_email'] ?? '');
    
    // Generate new random password
    $new_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 10);
    $hash = hashPassword($new_password);
    
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Update user password
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, must_change_password = 1 WHERE user_id = ?");
        $stmt->execute([$hash, $user_id]);
        
        // Mark request as completed
        $stmt = $pdo->prepare("UPDATE password_resets SET status = 'completed', resolved_by = ?, resolved_at = CURRENT_TIMESTAMP WHERE request_id = ?");
        $stmt->execute([$_SESSION['user_id'], $request_id]);
        
        $pdo->commit();
        
        // Simulate Email Sending
        // In a real environment: mail($email, "Password Reset", "Your new password is: $new_password");
        $success_message = "Password reset successfully. <br><strong>New Password: $new_password</strong><br><em>(In production, this would be emailed to $email)</em>";
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_message = "Failed to process reset: " . $e->getMessage();
    }
}

// Fetch Pending Password Resets
$pending_resets = [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT r.*, u.username, u.email, u.first_name, u.last_name 
                           FROM password_resets r 
                           JOIN users u ON r.user_id = u.user_id 
                           WHERE r.status = 'pending' 
                           ORDER BY r.request_date ASC");
    $stmt->execute();
    $pending_resets = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignore error if table doesn't exist yet (handled by setup script)
}

// Search and Filter logic
$search = sanitizeInput($_GET['search'] ?? '');
$role_filter = sanitizeInput($_GET['role_filter'] ?? '');

// Fetch all users with search
try {
    $pdo = getDBConnection();
    
    $where_clauses = ["u.deleted_at IS NULL"];
    $params = [];
    
    if (!empty($search)) {
        $where_clauses[] = "(u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($role_filter)) {
        $where_clauses[] = "r.role_name = ?";
        $params[] = $role_filter;
    }
    
    $where_sql = implode(" AND ", $where_clauses);
    
    $sql = "SELECT u.*, r.role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.role_id 
            WHERE $where_sql 
            ORDER BY u.created_at DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Failed to fetch users: ' . $e->getMessage();
}

// Fetch roles for dropdown
try {
    $stmt = $pdo->prepare("SELECT role_name FROM roles ORDER BY role_name");
    $stmt->execute();
    $roles = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Failed to fetch roles: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - User Management</title>
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
                        User Management
                    </h2>
                    
                    <!-- Display Messages -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    
                    <!-- Add New User Form -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="action" value="add_user">
                                
                                <div class="col-md-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name">
                                </div>
                                <div class="col-md-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name">
                                </div>
                                <div class="col-md-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone">
                                </div>
                                <div class="col-md-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select class="form-control" id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['role_name']; ?>">
                                                <?php echo ucfirst($role['role_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="showAddPassword" onchange="togglePassword('password', this.checked)">
                                        <label class="form-check-label" for="showAddPassword">Show</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-plus me-1"></i>Add User
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Search and Filter Section -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-search me-2"></i>Search & Filter</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-6">
                                    <label for="search" class="form-label">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               value="<?php echo $search; ?>" placeholder="Search by username, name, or email...">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label for="role_filter" class="form-label">Role Filter</label>
                                    <select class="form-control" id="role_filter" name="role_filter">
                                        <option value="">All Roles</option>
                                        <?php foreach ($roles as $role_choice): ?>
                                            <option value="<?php echo $role_choice['role_name']; ?>" 
                                                    <?php echo $role_filter == $role_choice['role_name'] ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($role_choice['role_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end gap-2">
                                    <button type="submit" class="btn btn-secondary flex-grow-1">
                                        Search
                                    </button>
                                    <a href="users.php" class="btn btn-outline-secondary">
                                        Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Pending Password Resets -->
                    <?php if (!empty($pending_resets)): ?>
                    <div class="card mb-4 border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-key me-2"></i>Pending Password Reset Requests</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>User</th>
                                            <th>Email</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_resets as $request): ?>
                                            <tr>
                                                <td><?php echo $request['request_date']; ?></td>
                                                <td><?php echo sanitizeInput($request['username']); ?></td>
                                                <td><?php echo sanitizeInput($request['email']); ?></td>
                                                <td>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Generate new password and send to user?');">
                                                        <input type="hidden" name="action" value="process_reset">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                        <input type="hidden" name="reset_user_id" value="<?php echo $request['user_id']; ?>">
                                                        <input type="hidden" name="reset_email" value="<?php echo $request['email']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-paper-plane me-1"></i>Reset & Send
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    
                    <!-- Users Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-users me-2"></i>All Users</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Full Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($users)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No users found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td><?php echo sanitizeInput($user['username']); ?></td>
                                                    <td><?php echo sanitizeInput($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                                    <td><?php echo sanitizeInput($user['email']); ?></td>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            <?php echo ucfirst(sanitizeInput($user['role_name'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($user['is_locked']): ?>
                                                            <span class="badge bg-danger">Locked</span>
                                                        <?php elseif ($user['is_active']): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick='editUser(
                                                            <?php echo (int)$user["user_id"]; ?>, 
                                                            <?php echo htmlspecialchars(json_encode($user["username"] ?? "")); ?>, 
                                                            <?php echo htmlspecialchars(json_encode($user["email"] ?? "")); ?>, 
                                                            <?php echo htmlspecialchars(json_encode($user["role_name"] ?? "")); ?>,
                                                            <?php echo htmlspecialchars(json_encode($user["first_name"] ?? "")); ?>,
                                                            <?php echo htmlspecialchars(json_encode($user["last_name"] ?? "")); ?>,
                                                            <?php echo htmlspecialchars(json_encode($user["phone"] ?? "")); ?>,
                                                            <?php echo (int)($user["is_active"] ?? 1); ?>,
                                                            <?php echo (int)($user["is_locked"] ?? 0); ?>
                                                        )'>
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                            <button class="btn btn-sm btn-outline-danger" onclick='deleteUser(<?php echo (int)$user["user_id"]; ?>, <?php echo htmlspecialchars(json_encode($user["username"] ?? "")); ?>)'>
                                                                <i class="fas fa-trash"></i>
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

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="edit_phone" name="phone">
                        </div>

                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-control" id="edit_role" name="role" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['role_name']; ?>">
                                        <?php echo ucfirst($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                    <label class="form-check-label" for="edit_is_active">Active</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="edit_is_locked" name="is_locked">
                                    <label class="form-check-label" for="edit_is_locked">Locked</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="showEditPassword" onchange="togglePassword('edit_password', this.checked)">
                                <label class="form-check-label" for="showEditPassword">Show Password</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete user "<span id="delete_username"></span>"?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <form method="POST">
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/modals.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword(inputId, show) {
            const input = document.getElementById(inputId);
            input.type = show ? 'text' : 'password';
        }

        function editUser(userId, username, email, role, firstName, lastName, phone, isActive, isLocked) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_first_name').value = firstName;
            document.getElementById('edit_last_name').value = lastName;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_is_active').checked = isActive == 1;
            document.getElementById('edit_is_locked').checked = isLocked == 1;
            document.getElementById('edit_password').value = '';
            document.getElementById('showEditPassword').checked = false;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
        
        function deleteUser(userId, username) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_username').textContent = username;
            
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }
    </script>
</body>
</html> 