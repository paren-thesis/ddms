<?php
/**
 * HTU COMPSSA CODEFEST 2025 - Login Window
 * First window shown on startup
 * 
 * Features:
 * - User login with username/password
 * - Navigation to registration and password change
 * - Encrypted password storage
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

$error_message = '';
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    handleLogin();
}

function handleLogin() {
    global $error_message;
    
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
        return;
    }
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT u.user_id, u.username, u.password_hash, u.email, u.is_locked, u.must_change_password, r.role_name 
                               FROM users u 
                               JOIN roles r ON u.role_id = r.role_id 
                               WHERE u.username = ? AND u.is_active = 1 AND u.deleted_at IS NULL");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            if ($user['is_locked']) {
                $error_message = 'Your account is locked. Please contact the administrator.';
                return;
            }
            
            if (verifyPassword($password, $user['password_hash'])) {
                // Reset login attempts on success
                $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);
                
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role_name'];
                $_SESSION['email'] = $user['email'];
                
                logActivity('LOGIN_SUCCESS', 'users', $user['user_id']);
                
                if ($user['must_change_password']) {
                    $_SESSION['must_change_password'] = true;
                    // No redirect, user goes to dashboard with a prompt
                }
                
                // Redirect to control window
                redirect('control.php');
            } else {
                // Increment login attempts
                $stmt = $pdo->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);
                
                // Check if should lock
                $stmt = $pdo->prepare("SELECT login_attempts FROM users WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);
                $attempts = $stmt->fetchColumn();
                
                if ($attempts >= 5) {
                    $stmt = $pdo->prepare("UPDATE users SET is_locked = 1 WHERE user_id = ?");
                    $stmt->execute([$user['user_id']]);
                    logActivity('ACCOUNT_LOCKED', 'users', $user['user_id'], null, ['username' => $username, 'reason' => 'Too many failed attempts']);
                    $error_message = 'Invalid password. Account has been locked after 5 failed attempts.';
                } else {
                    logActivity('LOGIN_FAILED', 'users', $user['user_id'], null, ['username' => $username]);
                    $error_message = 'Invalid username or password.';
                }
            }
        } else {
            $error_message = 'Invalid username or password.';
        }
    } catch (PDOException $e) {
        $error_message = 'Login failed. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Login</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                <div class="col-md-2"></div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    
                    <!-- Display Messages -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    
                    <!-- Login Form -->
                    <div class="form-container">
                        <h2 class="form-title">Login</h2>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="login">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="showPassword" onchange="togglePassword('password', this.checked)">
                                    <label class="form-check-label" for="showPassword">Show Password</label>
                                </div>
                            </div>
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary">Login</button>
                            </div>
                        </form>
                        
                        <!-- Navigation Buttons -->
                        <div class="row">
                            <div class="col-12 text-center">
                                <a href="change_password.php" class="btn btn-outline-secondary w-100">Change Password</a>
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
        function togglePassword(inputId, show) {
            const input = document.getElementById(inputId);
            input.type = show ? 'text' : 'password';
        }
    </script>
</body>
</html> 