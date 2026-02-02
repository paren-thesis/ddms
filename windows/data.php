<?php
/**
 * HTU COMPSSA CODEFEST 2025 - Data Window (Students)
 * Student data management and CSV import functionality
 * 
 * Features:
 * - Import data from CSV with auto/manual correction
 * - Add new students
 * - Edit existing student data
 * - Search functionality
 * - Display student data
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and has permission
if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'hod', 'cashier', 'supervisor', 'student'])) {
    redirect('login.php');
}

$error_message = '';
$success_message = '';
$students = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Debug: Log the action and user role
    error_log("Form submission - Action: $action, User Role: " . ($_SESSION['user_role'] ?? 'none'));
    
    // Role-based action handling
    if ($action === 'update_profile' && $_SESSION['user_role'] === 'student') {
        handleUpdateProfile();
    } elseif (in_array($_SESSION['user_role'], ['admin', 'hod'])) {
        switch ($action) {
            case 'import_csv':
                handleCSVImport();
                break;
            case 'add_student':
                handleAddStudent();
                break;
            case 'edit_student':
                handleEditStudent();
                break;
            case 'delete_student':
                handleDeleteStudent();
                break;
            default:
                $error_message = 'Invalid action specified.';
                break;
        }
    } else {
        $error_message = 'You do not have permission to perform this action.';
    }
}

function handleCSVImport() {
    global $error_message, $success_message;
    
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = 'Please select a valid CSV file.';
        return;
    }
    
    $file = $_FILES['csv_file'];
    $filename = $file['name'];
    $tmp_name = $file['tmp_name'];
    
    // Validate file type
    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($file_extension !== 'csv') {
        $error_message = 'Please upload a CSV file.';
        return;
    }
    
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Read CSV file
        $handle = fopen($tmp_name, 'r');
        if (!$handle) {
            $error_message = 'Unable to read the CSV file.';
            return;
        }

        // 0. Verify current session user exists (in case of DB reset/wipe)
        $session_user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND deleted_at IS NULL");
        $stmt->execute([$session_user_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Your session is stale (user ID not found). Please log out and log back in to refresh your account access.");
        }
        
        // Skip header row
        $header = fgetcsv($handle);
        $imported_count = 0;
        $errors = [];
        
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 14) {
                $errors[] = "Row has insufficient data (expected 14 columns)";
                continue;
            }
            
            // Map CSV data based on header: 
            // Name,Index No,Program Level,Session,Programme Of Study,Password,Phone,Academic Year,Dues payed,Recept No,Payment Date,Position ,Status,Email
            $name = sanitizeInput($data[0] ?? '');
            $index_no = sanitizeInput($data[1] ?? '');
            $prog_level = (int)sanitizeInput($data[2] ?? '100');
            $session_type = sanitizeInput($data[3] ?? 'Regular');
            $programme_name = sanitizeInput($data[4] ?? '');
            $csv_password = $data[5] ?? $index_no;
            $phone = sanitizeInput($data[6] ?? '');
            $academic_year = sanitizeInput($data[7] ?? '');
            $dues_paid = (float)sanitizeInput($data[8] ?? '0');
            $receipt_no = sanitizeInput($data[9] ?? '');
            $payment_date_raw = sanitizeInput($data[10] ?? '');
            $position = strtolower(trim(sanitizeInput($data[11] ?? 'student')));
            $status = sanitizeInput($data[12] ?? 'Active');
            $email = sanitizeInput($data[13] ?? '');
            
            // Validate required fields
            if (empty($index_no) || empty($email)) {
                $errors[] = "Row missing required data: Index No or Email for $name";
                continue;
            }
            
            // 1. Handle User Account Creation
            $user_id = null;
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$index_no, $email]);
            $existing_user = $stmt->fetch();
            
            if (!$existing_user) {
                // Get role_id based on position
                $role_stmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = ?");
                $role_stmt->execute([$position]);
                $role_data = $role_stmt->fetch();
                
                if (!$role_data) {
                    // Fallback to student role
                    $role_stmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'student'");
                    $role_stmt->execute();
                    $role_data = $role_stmt->fetch();
                }
                $role_id = $role_data ? $role_data['role_id'] : null;
                
                if (!$role_id) {
                    throw new Exception("Critical error: 'student' role not found in database.");
                }
                
                // Split name for user record
                $name_parts = explode(',', $name);
                $ln = trim($name_parts[0] ?? '');
                $fn = trim($name_parts[1] ?? '');

                // Create user (username is Index No)
                $hashed_password = password_hash($csv_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, phone, first_name, last_name, role_id, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)");
                $stmt->execute([$index_no, $hashed_password, $email, $phone, $fn, $ln, $role_id]);
                $user_id = $pdo->lastInsertId();
            } else {
                $user_id = $existing_user['user_id'];
            }
            
            // 2. Handle Student Data
            // Check if student already exists
            $stmt = $pdo->prepare("SELECT student_id FROM students WHERE index_no = ?");
            $stmt->execute([$index_no]);
            if ($stmt->fetch()) {
                // Update existing student or log error
                // For now, let's skip if exists as per original logic but we might want to update
                //$errors[] = "Student with Index No $index_no already exists";
            } else {
                // Get or create programme
                $stmt = $pdo->prepare("SELECT programme_id FROM programmes WHERE programme_name = ? OR programme_code = ?");
                $stmt->execute([$programme_name, $programme_name]);
                $programme_data = $stmt->fetch();
                
                if (!$programme_data) {
                    // Try to infer code and type
                    $code = strtoupper(str_replace(' ', '-', $programme_name));
                    $type = 'BTech'; // Default
                    if (stripos($programme_name, 'HND') !== false) $type = 'HND';
                    
                    $stmt = $pdo->prepare("INSERT INTO programmes (programme_code, programme_name, programme_type) VALUES (?, ?, ?)");
                    $stmt->execute([$code, $programme_name, $type]);
                    $programme_id = $pdo->lastInsertId();
                } else {
                    $programme_id = $programme_data['programme_id'];
                }
                
                // Split name (assuming "Lastname, Firstname")
                if (!isset($fn)) {
                    $name_parts = explode(',', $name);
                    $ln = trim($name_parts[0] ?? '');
                    $fn = trim($name_parts[1] ?? '');
                }
                
                // Insert student
                $stmt = $pdo->prepare("INSERT INTO students (index_no, first_name, last_name, email, phone, programme_id, programme_level, session_type, current_academic_year, position, status, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$index_no, $fn, $ln, $email, $phone, $programme_id, $prog_level, $session_type, $academic_year, $position, $status, $user_id]);
                $student_id = $pdo->lastInsertId();
                
                // 3. Handle Initial Payment (if provided in CSV)
                if (!empty($dues_paid) && $dues_paid > 0 && !empty($receipt_no)) {
                    // Check if receipt already exists
                    $stmt = $pdo->prepare("SELECT payment_id FROM payments WHERE receipt_no = ?");
                    $stmt->execute([$receipt_no]);
                    if (!$stmt->fetch()) {
                        // Format date (CSV is DD.MM.YYYY or similar, SQL needs YYYY-MM-DD)
                        $date_obj = date_create_from_format('d.m.Y', $payment_date_raw);
                        $sql_date = $date_obj ? $date_obj->format('Y-m-d') : date('Y-m-d');
                        
                        // Insert Payment
                        $stmt = $pdo->prepare("INSERT INTO payments (receipt_no, student_id, academic_year, total_amount, amount_paid, balance, payment_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$receipt_no, $student_id, $academic_year, $dues_paid, $dues_paid, 0, $sql_date, $session_user_id]);
                        $payment_id = $pdo->lastInsertId();
                        
                        // Create a default due if it doesn't exist for this year/programme
                        $stmt = $pdo->prepare("SELECT due_id FROM dues WHERE academic_year = ? AND (programme_id = ? OR programme_id IS NULL) LIMIT 1");
                        $stmt->execute([$academic_year, $programme_id]);
                        $due_data = $stmt->fetch();
                        
                        if ($due_data) {
                            $due_id = $due_data['due_id'];
                        } else {
                            // Create generic due
                            $due_code = "DEPT-" . $academic_year . "-" . $student_id;
                            $stmt = $pdo->prepare("INSERT INTO dues (due_name, due_code, amount, academic_year, created_by) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute(["Departmental Dues $academic_year", $due_code, $dues_paid, $academic_year, $session_user_id]);
                            $due_id = $pdo->lastInsertId();
                        }
                        
                        // Insert Payment Item
                        $stmt = $pdo->prepare("INSERT INTO payment_items (payment_id, due_id, amount, academic_year, description) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$payment_id, $due_id, $dues_paid, $academic_year, "Initial payment from import"]);
                    }
                }
                
                $imported_count++;
            }
            // Reset name parts for next iteration
            unset($fn);
            unset($ln);
        }
        
        fclose($handle);
        $pdo->commit();
        
        if ($imported_count > 0) {
            $success_message = "Successfully imported $imported_count students.";
            if (!empty($errors)) {
                $success_message .= " Some errors occurred: " . implode(', ', array_slice($errors, 0, 5));
            }
            logActivity('CSV_IMPORT', 'students', null, null, ['count' => $imported_count, 'filename' => $filename]);
        } else {
            $error_message = "No new students were imported. " . implode(', ', array_slice($errors, 0, 5));
        }
        
    } catch (Exception $e) {
        if (isset($pdo)) $pdo->rollBack();
        $error_message = 'Import failed: ' . $e->getMessage();
    }
}

function handleAddStudent() {
    global $error_message, $success_message;
    
    $index_no = sanitizeInput($_POST['index_no'] ?? '');
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $prog_level = (int)sanitizeInput($_POST['programme_level'] ?? '100');
    $session_type = sanitizeInput($_POST['session_type'] ?? 'Regular');
    $academic_year = sanitizeInput($_POST['current_academic_year'] ?? '');
    $programme_id = sanitizeInput($_POST['programme_id'] ?? '');
    
    if (empty($index_no) || empty($first_name) || empty($email)) {
        $error_message = 'Please fill in all required fields.';
        return;
    }
    
    try {
        $pdo = getDBConnection();
        
        // Check if student already exists
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE (index_no = ? OR email = ?) AND deleted_at IS NULL");
        $stmt->execute([$index_no, $email]);
        if ($stmt->fetch()) {
            $error_message = 'Student with this Index No or Email already exists.';
            return;
        }
        
        // Insert new student
        $stmt = $pdo->prepare("INSERT INTO students (index_no, first_name, last_name, email, phone, programme_id, programme_level, session_type, current_academic_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$index_no, $first_name, $last_name, $email, $phone, $programme_id, $prog_level, $session_type, $academic_year]);
        
        $new_student_id = $pdo->lastInsertId();
        logActivity('ADD_STUDENT', 'students', $new_student_id, null, ['index_no' => $index_no, 'name' => "$first_name $last_name"]);
        
        $success_message = 'Student added successfully!';
        
    } catch (PDOException $e) {
        $error_message = 'Failed to add student: ' . $e->getMessage();
    }
}

function handleEditStudent() {
    global $error_message, $success_message;
    
    $student_id = sanitizeInput($_POST['student_id'] ?? '');
    $index_no = sanitizeInput($_POST['index_no'] ?? '');
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $prog_level = (int)sanitizeInput($_POST['programme_level'] ?? '100');
    $session_type = sanitizeInput($_POST['session_type'] ?? 'Regular');
    $academic_year = sanitizeInput($_POST['current_academic_year'] ?? '');
    $programme_id = sanitizeInput($_POST['programme_id'] ?? '');
    
    if (empty($student_id) || empty($index_no) || empty($first_name) || empty($email)) {
        $error_message = 'Please fill in all required fields.';
        return;
    }
    
    try {
        $pdo = getDBConnection();
        
        // Check if email/index_no already exists for other students
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE (index_no = ? OR email = ?) AND student_id != ? AND deleted_at IS NULL");
        $stmt->execute([$index_no, $email, $student_id]);
        if ($stmt->fetch()) {
            $error_message = 'Student with this Index No or Email already exists.';
            return;
        }
        
        // Update student
        $stmt = $pdo->prepare("UPDATE students SET index_no = ?, first_name = ?, last_name = ?, email = ?, phone = ?, programme_id = ?, programme_level = ?, session_type = ?, current_academic_year = ? WHERE student_id = ?");
        $stmt->execute([$index_no, $first_name, $last_name, $email, $phone, $programme_id, $prog_level, $session_type, $academic_year, $student_id]);
        
        logActivity('EDIT_STUDENT', 'students', $student_id, null, ['index_no' => $index_no]);
        
        $success_message = 'Student updated successfully!';
        
    } catch (PDOException $e) {
        $error_message = 'Failed to update student: ' . $e->getMessage();
    }
}

function handleDeleteStudent() {
    global $error_message, $success_message;
    
    $student_id = sanitizeInput($_POST['student_id'] ?? '');
    
    if (empty($student_id)) {
        $error_message = 'Invalid student ID.';
        return;
    }
    
    try {
        $pdo = getDBConnection();
        
        // Check if student has payments (optional, but good for data integrity even with soft deletes)
        // Actually, soft delete is usually fine.
        
        // Soft delete student
        $stmt = $pdo->prepare("UPDATE students SET deleted_at = CURRENT_TIMESTAMP WHERE student_id = ?");
        $stmt->execute([$student_id]);
        
        logActivity('DELETE_STUDENT', 'students', $student_id);
        
        $success_message = 'Student record archived successfully!';
        
    } catch (PDOException $e) {
        $error_message = 'Failed to delete student: ' . $e->getMessage();
    }
}

function handleUpdateProfile() {
    global $error_message, $success_message;
    
    $student_id = sanitizeInput($_POST['student_id'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    if (empty($student_id) || empty($phone)) {
        $error_message = 'Phone number cannot be empty.';
        return;
    }
    
    try {
        $pdo = getDBConnection();
        
        // Verify this student belongs to the logged in user
        $stmt = $pdo->prepare("SELECT student_id, phone FROM students WHERE student_id = ? AND user_id = ?");
        $stmt->execute([$student_id, $user_id]);
        $old_data = $stmt->fetch();
        
        if (!$old_data) {
            $error_message = 'Unauthorized profile update attempt.';
            return;
        }
        
        // Update phone
        $stmt = $pdo->prepare("UPDATE students SET phone = ? WHERE student_id = ? AND user_id = ?");
        $stmt->execute([$phone, $student_id, $user_id]);
        
        logActivity('PROFILE_UPDATE', 'students', $student_id, ['phone' => $old_data['phone']], ['phone' => $phone]);
        
        $success_message = 'Contact information updated successfully!';
        
    } catch (PDOException $e) {
        $error_message = 'Failed to update profile: ' . $e->getMessage();
    }
}

// Get search parameters
$search = sanitizeInput($_GET['search'] ?? '');
$programme_filter = sanitizeInput($_GET['programme'] ?? '');
$year_filter = sanitizeInput($_GET['year'] ?? '');

// Fetch students with search and filters
try {
    $pdo = getDBConnection();
    
    $where_conditions = ["s.deleted_at IS NULL"]; // Exclude soft-deleted records
    $params = [];
    
    // If user is a student, only show their own record
    if ($_SESSION['user_role'] === 'student') {
        $where_conditions[] = "s.user_id = ?";
        $params[] = $_SESSION['user_id'];
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(s.index_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    if (!empty($programme_filter)) {
        $where_conditions[] = "s.programme_id = ?";
        $params[] = $programme_filter;
    }
    
    if (!empty($year_filter)) {
        $where_conditions[] = "s.current_academic_year = ?";
        $params[] = $year_filter;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "SELECT s.*, p.programme_name, p.programme_code 
            FROM students s 
            LEFT JOIN programmes p ON s.programme_id = p.programme_id 
            $where_clause 
            ORDER BY s.first_name, s.last_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = 'Failed to fetch students: ' . $e->getMessage();
}

// Get programmes for filter and forms
try {
    $stmt = $pdo->prepare("SELECT programme_id, programme_name, programme_code FROM programmes WHERE status = 'Active' ORDER BY programme_name");
    $stmt->execute();
    $programmes = $stmt->fetchAll();
} catch (PDOException $e) {
    $programmes = [];
}

// Get academic years for filter
try {
    $stmt = $pdo->prepare("SELECT DISTINCT current_academic_year FROM students WHERE deleted_at IS NULL ORDER BY current_academic_year DESC");
    $stmt->execute();
    $academic_years = $stmt->fetchAll();
} catch (PDOException $e) {
    $academic_years = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Student Data</title>
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
                        Student Data Management
                    </h2>
                    
                    <!-- Display Messages -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    
                    <!-- Import CSV Section - Only for Admin, HOD, and Supervisor -->
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'hod', 'supervisor'])): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Import CSV Data</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="import_csv">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="csv_file" class="form-label">Select CSV File</label>
                                            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                                            <small class="text-muted">Maximum file size: 5MB. File should contain: Name, Index No, Email, Phone, Academic Year, Programme</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-upload me-2"></i>Import CSV
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Student View & Profile Profile -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>My Profile Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Personal View:</strong> You are viewing your official student record. Only you and department officials can see this data.
                            </div>
                            
                            <!-- Phone Number Update Form -->
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="action" value="update_profile">
                                <input type="hidden" name="student_id" value="<?php echo $students[0]['student_id'] ?? ''; ?>">
                                <div class="row align-items-end">
                                    <div class="col-md-6">
                                        <label for="new_phone" class="form-label">Update Phone Number</label>
                                        <input type="text" class="form-control" id="new_phone" name="phone" 
                                               value="<?php echo sanitizeInput($students[0]['phone'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Update Contact Info
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Search and Filter Section - Hidden for Students -->
                    <?php if ($_SESSION['user_role'] !== 'student'): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-search me-2"></i>Search & Filter</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="search" class="form-label">Search</label>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               value="<?php echo $search; ?>" placeholder="Search by name, index, email...">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
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
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="year" class="form-label">Academic Year</label>
                                        <select class="form-control" id="year" name="year">
                                            <option value="">All Years</option>
                                            <?php foreach ($academic_years as $yr): ?>
                                                <option value="<?php echo $yr['current_academic_year']; ?>" 
                                                        <?php echo $year_filter == $yr['current_academic_year'] ? 'selected' : ''; ?>>
                                                    <?php echo $yr['current_academic_year']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-secondary">
                                            <i class="fas fa-search me-2"></i>Search
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Add New Student Section - Only for Admin, HOD, and Supervisor -->
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'hod', 'supervisor'])): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add New Student</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_student">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="index_no" class="form-label">Index No *</label>
                                            <input type="text" class="form-control" id="index_no" name="index_no" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="first_name" class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="last_name" class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email *</label>
                                            <input type="email" class="form-control" id="email" name="email" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Phone</label>
                                            <input type="text" class="form-control" id="phone" name="phone">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label for="programme_level" class="form-label">Level</label>
                                            <select class="form-control" id="programme_level" name="programme_level">
                                                <option value="100">100</option>
                                                <option value="200">200</option>
                                                <option value="300">300</option>
                                                <option value="400">400</option>
                                                <option value="Top-Up">Top-Up</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label for="session_type" class="form-label">Session</label>
                                            <select class="form-control" id="session_type" name="session_type">
                                                <option value="Regular">Regular</option>
                                                <option value="Weekend">Weekend</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label for="current_academic_year" class="form-label">Academic Year</label>
                                            <input type="text" class="form-control" id="current_academic_year" name="current_academic_year" 
                                                   value="<?php echo getCurrentAcademicYear(); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label for="programme_id" class="form-label">Programme</label>
                                            <select class="form-control" id="programme_id" name="programme_id">
                                                <option value="">Select Programme</option>
                                                <?php foreach ($programmes as $prog): ?>
                                                    <option value="<?php echo $prog['programme_id']; ?>">
                                                        <?php echo $prog['programme_name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="d-grid pt-4">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i>Add
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Students Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Student Records (<?php echo count($students); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Index No</th>
                                            <th>Name</th>
                                            <th>Level</th>
                                            <th>Session</th>
                                            <th>Programme</th>
                                            <th>Academic Year</th>
                                            <th>Status</th>
                                            <?php if (!in_array($_SESSION['user_role'], ['student'])): ?>
                                            <th>Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($students)): ?>
                                            <tr>
                                                <td colspan="<?php echo in_array($_SESSION['user_role'], ['student']) ? '7' : '8'; ?>" class="text-center">No students found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($students as $student): ?>
                                                <tr>
                                                    <td><?php echo sanitizeInput($student['index_no']); ?></td>
                                                    <td><?php echo sanitizeInput($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                                    <td><?php echo sanitizeInput($student['programme_level']); ?></td>
                                                    <td><?php echo sanitizeInput($student['session_type']); ?></td>
                                                    <td><?php echo sanitizeInput($student['programme_name']); ?></td>
                                                    <td><?php echo sanitizeInput($student['current_academic_year']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $student['status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                                            <?php echo sanitizeInput($student['status']); ?>
                                                        </span>
                                                    </td>
                                                    <?php if (!in_array($_SESSION['user_role'], ['student'])): ?>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary" onclick="editStudent(
                                                            <?php echo $student['student_id']; ?>,
                                                            '<?php echo addslashes($student['index_no']); ?>',
                                                            '<?php echo addslashes($student['first_name']); ?>',
                                                            '<?php echo addslashes($student['last_name']); ?>',
                                                            '<?php echo addslashes($student['email']); ?>',
                                                            '<?php echo addslashes($student['phone']); ?>',
                                                            '<?php echo addslashes($student['current_academic_year']); ?>',
                                                            '<?php echo addslashes($student['programme_id']); ?>',
                                                            '<?php echo addslashes($student['programme_level']); ?>',
                                                            '<?php echo addslashes($student['session_type']); ?>'
                                                        )">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="deleteStudent(<?php echo $student['student_id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Student Modal -->
                    <div class="modal fade" id="editStudentModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Student</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="edit_student">
                                        <input type="hidden" name="student_id" id="edit_student_id">
                                        <div class="mb-3">
                                            <label for="edit_index_no" class="form-label">Index No *</label>
                                            <input type="text" class="form-control" id="edit_index_no" name="index_no" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit_first_name" class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit_last_name" class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit_email" class="form-label">Email *</label>
                                            <input type="email" class="form-control" id="edit_email" name="email" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit_phone" class="form-label">Phone</label>
                                            <input type="text" class="form-control" id="edit_phone" name="phone">
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="edit_programme_level" class="form-label">Level</label>
                                                    <select class="form-control" id="edit_programme_level" name="programme_level">
                                                        <option value="100">100</option>
                                                        <option value="200">200</option>
                                                        <option value="300">300</option>
                                                        <option value="400">400</option>
                                                        <option value="Top-Up">Top-Up</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="edit_session_type" class="form-label">Session</label>
                                                    <select class="form-control" id="edit_session_type" name="session_type">
                                                        <option value="Regular">Regular</option>
                                                        <option value="Weekend">Weekend</option>
                                                        <option value="Evening">Evening</option>
                                                        <option value="Distance">Distance</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit_current_academic_year" class="form-label">Academic Year</label>
                                            <input type="text" class="form-control" id="edit_current_academic_year" name="current_academic_year">
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit_programme_id" class="form-label">Programme</label>
                                            <select class="form-control" id="edit_programme_id" name="programme_id">
                                                <option value="">Select Programme</option>
                                                <?php foreach ($programmes as $prog): ?>
                                                    <option value="<?php echo $prog['programme_id']; ?>">
                                                        <?php echo $prog['programme_name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Update Student</button>
                                    </div>
                                </form>
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
        function editStudent(studentId, indexNo, firstName, lastName, email, phone, academicYear, programmeId, level, session) {
            console.log('Editing student:', {studentId, indexNo, firstName, lastName, email, phone, academicYear, programmeId, level, session});
            
            document.getElementById('edit_student_id').value = studentId;
            document.getElementById('edit_index_no').value = indexNo;
            document.getElementById('edit_first_name').value = firstName;
            document.getElementById('edit_last_name').value = lastName;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_current_academic_year').value = academicYear;
            document.getElementById('edit_programme_id').value = programmeId;
            document.getElementById('edit_programme_level').value = level;
            document.getElementById('edit_session_type').value = session;
            
            const modal = new bootstrap.Modal(document.getElementById('editStudentModal'));
            modal.show();
        }
        
        function deleteStudent(studentId) {
            console.log('Deleting student:', studentId);
            
            if (confirm('Are you sure you want to delete this student?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_student">
                    <input type="hidden" name="student_id" value="${studentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>