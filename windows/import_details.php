<?php
/**
 * HTU COMPSSA CODEFEST 2025 - Import Details View
 * Displays row-by-row results and auto-corrections from a JSON import log.
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and has permission
if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'hod'])) {
    redirect('login.php');
}

$log_file = $_GET['log'] ?? '';
$log_data = null;
$error_message = '';

if (empty($log_file)) {
    $error_message = 'No log file specified.';
} else {
    // Basic security: only allow files from logs/imports/ that match our pattern
    if (!preg_match('/^import_\d+_\d+\.json$/', $log_file)) {
        $error_message = 'Invalid log file format.';
    } else {
        $log_path = '../logs/imports/' . $log_file;
        if (file_exists($log_path)) {
            $json_content = file_get_contents($log_path);
            $log_data = json_decode($json_content, true);
            if (!$log_data) {
                $error_message = 'Failed to parse log file data.';
            }
        } else {
            $error_message = 'Log file not found.';
        }
    }
}

// Redirect if there's an error and we are too deep
if ($error_message && !isset($_GET['stay'])) {
    // Optionally redirect back to data.php with error?
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Import Details</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .correction-item {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 2px;
            display: block;
        }
        .correction-item i {
            color: var(--orange-brown);
            width: 15px;
        }
        .status-badge {
            min-width: 80px;
        }
        .summary-card {
            border-left: 5px solid;
        }
        .summary-success { border-left-color: #198754; color: #198754; }
        .summary-updated { border-left-color: #0dcaf0; color: #0dcaf0; }
        .summary-errors { border-left-color: #dc3545; color: #dc3545; }
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
                    <h1 class="app-title"><?php echo APP_NAME; ?></h1>
                </div>
                <div class="col-md-2 text-end">
                    <a href="data.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back to Data
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-file-import me-2"></i>Import Processing Details</h2>
                <?php if ($log_data): ?>
                <div class="text-muted small">
                    <strong>File:</strong> <?php echo htmlspecialchars($log_data['metadata']['filename']); ?><br>
                    <strong>Processed:</strong> <?php echo htmlspecialchars($log_data['metadata']['timestamp']); ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                </div>
            <?php elseif ($log_data): ?>
                
                <!-- Summary Section -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card shadow-sm h-100 p-3 border-0 bg-light">
                            <div class="small text-muted mb-1 text-uppercase fw-bold">Total Rows</div>
                            <div class="h3 mb-0"><?php echo $log_data['metadata']['summary']['total_rows']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm h-100 p-3 summary-card summary-success">
                            <div class="small mb-1 text-uppercase fw-bold">New Students</div>
                            <div class="h3 mb-0"><?php echo $log_data['metadata']['summary']['success']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm h-100 p-3 summary-card summary-updated">
                            <div class="small mb-1 text-uppercase fw-bold">Updated Records</div>
                            <div class="h3 mb-0"><?php echo $log_data['metadata']['summary']['updated']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm h-100 p-3 summary-card summary-errors">
                            <div class="small mb-1 text-uppercase fw-bold">Errors Skipped</div>
                            <div class="h3 mb-0"><?php echo $log_data['metadata']['summary']['errors']; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Table -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Row-by-Row Processing Log</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="80">Row</th>
                                        <th width="150">Index No</th>
                                        <th width="200">Name</th>
                                        <th width="120">Status</th>
                                        <th>Details & Auto-Corrections</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($log_data['results'] as $result): ?>
                                        <tr>
                                            <td class="text-center fw-bold"><?php echo $result['row']; ?></td>
                                            <td><code><?php echo htmlspecialchars($result['index_no']); ?></code></td>
                                            <td><?php echo htmlspecialchars($result['name']); ?></td>
                                            <td>
                                                <?php if ($result['status'] === 'success'): ?>
                                                    <span class="badge bg-success status-badge">New</span>
                                                <?php elseif ($result['status'] === 'warning'): ?>
                                                    <span class="badge bg-info status-badge text-white">Updated</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger status-badge">Error</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-bold mb-1 <?php echo $result['status'] === 'error' ? 'text-danger' : ''; ?>">
                                                    <?php echo htmlspecialchars($result['message']); ?>
                                                </div>
                                                
                                                <?php if (!empty($result['corrections'])): ?>
                                                    <div class="mt-2 border-top pt-1">
                                                        <?php foreach ($result['corrections'] as $correction): ?>
                                                            <span class="correction-item">
                                                                <i class="fas fa-magic"></i> <?php echo htmlspecialchars($correction); ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </main>

    <footer class="app-footer text-center py-3 bg-light mt-4">
        <div class="container">
            <span class="text-muted small">&copy; <?php echo date('Y'); ?> HTU COMPSSA CODEFEST. Detailed Import Data Log.</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
