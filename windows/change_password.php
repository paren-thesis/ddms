<?php
/**
 * HTU COMPSSA CODEFEST 2025 - Password Change Window
 * Password change functionality for existing users
 * 
 * Features:
 * - Current password verification
 * - New password with confirmation
 * - Encrypted password storage
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

$error_message = '';
$success_message = '';

// Handle password reset request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_reset') {
    handleRequestReset();
}

function handleRequestReset() {
    global $error_message, $success_message;
    
    $username = sanitizeInput($_POST['username'] ?? '');
    
    if (empty($username)) {
        $error_message = 'Please enter your username or index number.';
        return;
    }
    
    try {
        $pdo = getDBConnection();
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT user_id, email FROM users WHERE username = ? AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Check for existing pending request
            $stmt = $pdo->prepare("SELECT request_id FROM password_resets WHERE user_id = ? AND status = 'pending'");
            $stmt->execute([$user['user_id']]);
            if ($stmt->fetch()) {
                $error_message = 'You already have a pending request. Please wait for the administrator to process it.';
                return;
            }

            // Create reset request
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id) VALUES (?)");
            $stmt->execute([$user['user_id']]);
            
            $success_message = 'Your password reset request has been submitted. The administrator will review it and send you a new password.';
        } else {
            // Generic message for security (don't reveal if user exists)
            $success_message = 'If an account with that username exists, a reset request has been submitted.';
        }
        
    } catch (PDOException $e) {
        $error_message = 'Request failed. Please try again later.';
        error_log("Password reset request error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Change Password</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                <div class="col-md-2"></div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    
                    <!-- Display Messages -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    
                    <!-- Password Reset Request Form -->
                    <div class="form-container">
                        <h2 class="form-title">Request Password Reset</h2>
                        <p class="text-center text-muted mb-4">Enter your Username or Index Number to request a password reset from the Administrator.</p>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="request_reset">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username / Index Number</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <!-- 
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address (Optional verification)</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            -->
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary">Submit Request</button>
                            </div>
                        </form>
                        
                        <!-- Navigation Button -->
                        <div class="d-grid">
                            <a href="login.php" class="btn btn-secondary">Back to Login</a>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAllPasswords(show) {
            const fields = document.querySelectorAll('.password-field');
            fields.forEach(field => {
                field.type = show ? 'text' : 'password';
            });
        }
    </script>
</body>
</html> 