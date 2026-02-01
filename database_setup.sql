-- HTU COMPSSA CODEFEST 2025 - Complete Database Setup
-- Departmental Dues Management System (Overhaul Version)

CREATE DATABASE IF NOT EXISTS ddms_database;
USE ddms_database;

-- Drop existing tables in correct order
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS configurations;
DROP TABLE IF EXISTS payment_items;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS dues;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS academic_sessions;
DROP TABLE IF EXISTS programmes;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

-- 1. ROLES TABLE
CREATE TABLE roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    role_level INT NOT NULL DEFAULT 1,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. PROGRAMMES TABLE
CREATE TABLE programmes (
    programme_id INT PRIMARY KEY AUTO_INCREMENT,
    programme_code VARCHAR(20) NOT NULL UNIQUE,
    programme_name VARCHAR(100) NOT NULL,
    programme_type ENUM('BTech', 'HND', 'Diploma', 'Other') DEFAULT 'BTech',
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. ACADEMIC SESSIONS TABLE
CREATE TABLE academic_sessions (
    session_id INT PRIMARY KEY AUTO_INCREMENT,
    session_name VARCHAR(20) NOT NULL UNIQUE,
    is_current BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. USERS TABLE
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    role_id INT NOT NULL,
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    is_locked BOOLEAN DEFAULT FALSE,
    must_change_password BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

-- 5. STUDENTS TABLE
CREATE TABLE students (
    student_id INT PRIMARY KEY AUTO_INCREMENT,
    index_no VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    programme_id INT NOT NULL,
    programme_level INT NOT NULL DEFAULT 100,
    session_type ENUM('Regular', 'Weekend', 'Evening') DEFAULT 'Regular',
    current_academic_year VARCHAR(20),
    position VARCHAR(50) DEFAULT 'student',
    status ENUM('Active', 'Inactive', 'Graduated') DEFAULT 'Active',
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (programme_id) REFERENCES programmes(programme_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- 6. DUES TABLE
CREATE TABLE dues (
    due_id INT PRIMARY KEY AUTO_INCREMENT,
    due_name VARCHAR(100) NOT NULL,
    due_code VARCHAR(50) NOT NULL UNIQUE,
    programme_id INT NULL,
    amount DECIMAL(10,2) NOT NULL,
    academic_year VARCHAR(20),
    is_mandatory BOOLEAN DEFAULT TRUE,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    FOREIGN KEY (programme_id) REFERENCES programmes(programme_id)
);

-- 7. PAYMENTS TABLE
CREATE TABLE payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    receipt_no VARCHAR(50) NOT NULL UNIQUE,
    student_id INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    payment_date DATE NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- 8. PAYMENT ITEMS TABLE
CREATE TABLE payment_items (
    payment_item_id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL,
    due_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    academic_year VARCHAR(20),
    description VARCHAR(255),
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE CASCADE,
    FOREIGN KEY (due_id) REFERENCES dues(due_id)
);

-- 9. AUDIT LOGS TABLE
CREATE TABLE audit_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(50) NULL,
    record_id INT NULL,
    old_values TEXT NULL,
    new_values TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- 10. CONFIGURATION TABLE
CREATE TABLE configurations (
    config_id INT PRIMARY KEY AUTO_INCREMENT,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT,
    description TEXT
);

-- Insert Default Data
INSERT INTO roles (role_name, role_level, description) VALUES
('admin', 5, 'Full system access'),
('hod', 4, 'Head of Department - Management & Reports'),
('cashier', 3, 'Revenue and Payment Processing'),
('supervisor', 2, 'Departmental oversight and monitoring'),
('student', 1, 'Restricted student access');

INSERT INTO programmes (programme_code, programme_name) VALUES
('BTECH-ICT', 'BTech Information Communication Technology'),
('BTECH-CS', 'BTech Computer Science'),
('HND-ICT', 'HND Information Communication Technology'),
('HND-CS', 'HND Computer Science');

INSERT INTO academic_sessions (session_name, is_current) VALUES
('2023-2024', FALSE),
('2024-2025', TRUE);

INSERT INTO dues (due_name, due_code, amount, academic_year) VALUES
('Departmental Dues', 'DEPT-2024-2025', 150.00, '2024-2025'),
('Laboratory Fee', 'LAB-2024-2025', 50.00, '2024-2025');

INSERT INTO configurations (config_key, config_value, description) VALUES
('system_name', 'HTU COMPSSA Dues Management System', 'Name of the application'),
('receipt_prefix', 'CSD', 'Prefix for receipt numbers');

-- Default Admin (Password: password)
INSERT INTO users (username, password_hash, email, first_name, last_name, role_id, must_change_password) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@htu.edu.gh', 'System', 'Administrator', 1, FALSE);

-- Indexes
CREATE INDEX idx_student_index ON students(index_no);
CREATE INDEX idx_payment_receipt ON payments(receipt_no);
CREATE INDEX idx_user_username ON users(username);
