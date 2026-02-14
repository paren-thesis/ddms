# HTU Departmental Dues Management System (DDMS)

A comprehensive web-based application for managing departmental dues, student records, and payments for the **Ho Technical University Computer Science Students Association (COMPSSA)**.

---

## 📋 Table of Contents

1. [Overview](#-overview)
2. [Features](#-features)
3. [Technology Stack](#-technology-stack)
4. [Project Structure](#-project-structure)
5. [Database Schema](#-database-schema)
6. [Installation & Setup](#-installation--setup)
7. [User Roles & Permissions](#-user-roles--permissions)
8. [Module Documentation](#-module-documentation)
9. [Usage Guide](#-usage-guide)
10. [API & Functions Reference](#-api--functions-reference)
11. [Troubleshooting](#-troubleshooting)

---

## 🎯 Overview

The DDMS is a PHP-based web application developed for the HTU COMPSSA Codefest 2025. It provides a complete solution for:

- Managing student records and dues
- Processing and tracking payments
- Generating official PDF receipts
- Providing analytics and reporting
- Maintaining audit trails for all system activities

---

## ✨ Features

### Core Features
- **User Authentication**: Secure login with password hashing, account lockout after 5 failed attempts
- **Role-Based Access Control**: 5 distinct roles with granular permissions
- **Student Management**: CRUD operations, CSV bulk import with Mac/Windows support, search & filtering
- **Payment Processing**: "Review & Pay" preview step, auto-generate unique receipt numbers
- **Email Receipts**: Automated HTML payment receipts via PHPMailer
- **Password Reset System**: Student request flow with Admin fulfillment and email notifications
- **PDF Receipt Generation**: A4 dual-copy receipts (Student/Department) with FPDF
- **Reporting & Analytics**: Visual dashboards with Chart.js, CSV export
- **Audit Logging**: Complete activity tracking with before/after data snapshots

### Security Features
- Password hashing using `PASSWORD_DEFAULT` (bcrypt)
- PDO prepared statements (SQL injection protection)
- XSS protection via `htmlspecialchars()` sanitization
- Session management with timeout controls
- Soft delete for data recovery

---

## 🛠 Technology Stack

| Component | Technology |
|-----------|------------|
| **Backend** | PHP 8.0+ |
| **Database** | MySQL 8.0+ |
| **Frontend** | Bootstrap 5.3, HTML5, CSS3 |
| **PDF Generation** | FPDF Library |
| **Email Delivery** | PHPMailer 6.9 |
| **Icons** | Font Awesome 6.0 |
| **Server** | Apache (XAMPP) |

---

## 📁 Project Structure

```
ddms/
├── index.php                 # Entry point (redirects to login)
├── database_setup.sql        # Complete database schema
├── setup_db.php             # Database initialization script
├── students.csv             # Sample student data
│
├── config/
│   ├── config.php           # Application configuration
│   ├── database.php         # Database connection settings
│   ├── email.php            # SMTP email settings (PHPMailer)
│   └── example.email.php    # Email configuration template
│
├── libs/                    # External libraries
│   └── PHPMailer-6.9.3/     # PHPMailer source files
│
├── includes/
│   ├── functions.php        # Utility functions library
│   ├── email_helper.php     # Email sending functions
│   ├── header.php           # Common header component
│   ├── fpdf.php             # FPDF PDF library
│   ├── font/                # PDF fonts
│   └── doc/                 # FPDF documentation
│
├── windows/
│   ├── login.php            # Authentication
│   ├── control.php          # Dashboard & navigation
│   ├── data.php             # Student data management
│   ├── payment.php          # Payment processing
│   ├── report.php           # Reports & analytics
│   ├── settings.php         # Sessions & dues management
│   ├── users.php            # User administration
│   ├── audit_logs.php       # Activity logs viewer
│   ├── generate_receipt.php # PDF receipt generator
│   └── change_password.php  # Password management
│
├── css/
│   └── style.css            # Global styles (COMPSSA theme)
│
└── assets/
    ├── compssa_logo.png     # Application logo
    └── format/              # Receipt template assets
```

---

## 🗄 Database Schema

The system uses **10 normalized tables**:

| Table | Description |
|-------|-------------|
| `roles` | User role definitions (admin, hod, cashier, supervisor, student) |
| `users` | User accounts with authentication data |
| `programmes` | Academic programmes (BTech-ICT, HND-CS, etc.) |
| `academic_sessions` | Academic year management (2023-2024, 2024-2025) |
| `students` | Student personal and academic information |
| `dues` | Dues categories with amounts per academic year |
| `payments` | Payment transaction records |
| `payment_items` | Individual payment line items |
| `audit_logs` | System activity tracking |
| `configurations` | System settings key-value store |

### Entity Relationships

```
roles (1) ──────── (N) users
users (1) ──────── (0..1) students
programmes (1) ─── (N) students
students (1) ───── (N) payments
payments (1) ───── (N) payment_items
dues (1) ────────── (N) payment_items
users (1) ────────── (N) audit_logs
```

---

## 🚀 Installation & Setup

### Prerequisites
- XAMPP (PHP 8.0+ and MySQL 8.0+)
- Modern web browser (Chrome, Edge, Firefox)

### Step 1: Place Project Files
```
C:\xampp\htdocs\ddms
```

### Step 2: Start XAMPP Services
1. Open XAMPP Control Panel
2. Start **Apache** and **MySQL**

### Step 3: Initialize Database
1. Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Create database: `ddms_database`
3. Import: Select `database_setup.sql` from project root
4. Click **Import/Go**

### Step 4: Configure Email (SMTP)
1. Open `config/email.php`
2. Enter your Gmail address and **App Password**
3. Set `MAIL_ENABLED` to `true`

### Step 5: Access Application
Navigate to: [http://localhost/ddms](http://localhost/ddms)

### Default Credentials
| Role | Username | Password |
|------|----------|----------|
| Admin | `admin` | `password` |

---

## 👥 User Roles & Permissions

| Role | Level | Permissions |
|------|-------|-------------|
| **Admin** | 5 | Full system access - all modules |
| **HOD** | 4 | Students, Payments, Reports, Settings, Audit Logs |
| **Cashier** | 3 | Payments, Reports, Settings |
| **Supervisor** | 2 | Students (view), Payments (view), Reports, Audit Logs |
| **Student** | 1 | Own profile, Own payment history |

---

## 📖 Module Documentation

### 1. Login Module (`login.php`)
- Username/password authentication
- Account lockout after 5 failed attempts
- **Request Password Reset**: Students can submit reset requests
- Dashboard change password prompt for first-time login
- Activity logging (login success/failure)

### 2. Control Panel (`control.php`)
- Role-based navigation cards
- Quick statistics (students, payments, revenue)
- Revenue trend chart (last 6 months)
- Payment completion doughnut chart

### 3. Student Data Module (`data.php`)
- CSV bulk import with auto-correction
- Add/Edit/Delete student records
- Search by name, index no, email
- Filter by programme and academic year
- Students can update their own phone number

### 4. Payment Module (`payment.php`)
- Searchable student selection
- Due category selection with auto-fill amount
- **Review & Pay**: Verification modal before submission
- **Email Confirmation**: Automatic digital receipt delivery
- Transaction recording with unique receipt numbers
- Payment history with filtering
- Students see their balance summary

### 5. Reports Module (`report.php`)
- Programme enrollment bar chart
- Level distribution pie chart
- Student payment summary table
- CSV export functionality
- Print-friendly layout

### 6. Settings Module (`settings.php`)
- Academic session management
- Set current academic year
- Dues category CRUD operations
- Programme-specific dues support

### 7. User Management (`users.php`)
- Admin-only access
- Create/Edit/Delete users
- **Reset Requests**: View and fulfill pending password resets
- Role assignment
- Account lock/unlock controls
- Soft delete (archival)

### 8. Audit Logs (`audit_logs.php`)
- Filter by action, user, date range
- View old/new values for changes
- IP address and user agent tracking
- Up to 500 entries displayed

### 9. Receipt Generator (`generate_receipt.php`)
- FPDF-based PDF generation
- A4 portrait dual-copy layout
- Amount in words conversion
- Official COMPSSA branding

---

## 📝 Usage Guide

### First-Time Setup
1. Login as admin
2. Go to **Settings** → Verify current academic year
3. Add/edit **Dues Categories** for the current session

### Processing a Payment
1. Go to **Payment** window
2. Search for student (by index or name)
3. Select due category
4. Enter amount and date
5. Click **Review & Pay** and verify details
6. Click **Confirm & Process**
7. Receipt is emailed automatically; print PDF if needed

### Importing Students (CSV)
1. Go to **Data** window
2. Upload CSV file with format (see [CSV Import Guide](CSV_IMPORT_GUIDE.md))
3. Email auto-generates if missing (`index@htu.edu.gh`)
4. System auto-creates user accounts with index number as username

---

## 🔧 API & Functions Reference

### Core Utility Functions (`includes/functions.php`)

| Function | Description |
|----------|-------------|
| `sanitizeInput($input)` | XSS protection via htmlspecialchars |
| `hashPassword($password)` | Create bcrypt hash |
| `verifyPassword($password, $hash)` | Verify password |
| `generateReceiptNumber()` | Create unique receipt ID |
| `isLoggedIn()` | Check session state |
| `hasRole($role)` | Verify user role |
| `redirect($url)` | HTTP redirect |
| `formatCurrency($amount)` | Format as "GHC X.XX" |
| `getCurrentAcademicYear()` | Get current session |
| `numberToWords($number)` | Convert amount to words |
| `logActivity($action, ...)` | Create audit log entry |
| `sendEmail($to, $sub, $body)` | Send generic PHPMailer email |
| `sendPaymentReceiptEmail()` | Send branded receipt email |
| `sendPasswordResetEmail()` | Send reset password notification |

### Database Connection (`config/database.php`)

```php
$pdo = getDBConnection();
```

Returns a PDO instance with:
- Exception mode enabled
- Associative fetch mode
- UTF-8 charset

---

## 🔍 Troubleshooting

| Issue | Solution |
|-------|----------|
| **PDF Not Loading** | Ensure `includes/fpdf.php` exists; check for PHP warnings |
| **Database Error** | Verify credentials in `config/database.php` |
| **Login Issues** | Clear browser cache; check if account is locked |
| **Incorrect Logo** | Clear cache; verify `assets/compssa_logo.png` exists |
| **Session Timeout** | Re-login; adjust `SESSION_TIMEOUT` in config |
| **SMTP Error** | Verify Gmail App Password in `config/email.php` |
| **CSV Row Mismatch** | Ensure at least 13 columns; check line endings |

---

## 📄 License

Built for **HTU COMPSSA Codefest 2025**

---

## 👨‍💻 Technical Contacts

For technical support or contributions, contact the COMPSSA IT team.
