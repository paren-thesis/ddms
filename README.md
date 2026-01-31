# HTU COMPSSA CODEFEST 2025 - Departmental Dues Management System (DDMS)

## Project Overview

This is a professional web-based departmental dues management system developed for HTU COMPSSA CODEFEST 2025. The system provides a comprehensive solution for managing academic sessions, student records, fee categories, and payment processing with high-fidelity PDF receipt generation and real-time visual analytics.

## Features

- **Multi-role Authentication System**: Administrator, Supervisor, Lecturer, and Student roles with fine-grained permissions.
- **Visual Analytics Dashboard**: Real-time revenue trends and payment completion charts using Chart.js on the main control panel.
- **Student Data Management**: Advanced search, bulk CSV import, and soft-delete support for student records.
- **Automated PDF Receipts**: Professional, dual-copy A4 receipts (Student & Department copies) generated automatically upon payment.
- **Dues & Session Management**: Dynamic management of academic years/sessions and fee categories (mandatory vs. optional).
- **Security & Auditing**: Comprehensive audit logs tracking every sensitive action (Data edits, Payments, Settings) with old/new value snapshots.
- **Responsive Design**: Modern, premium UI following the COMPSSA style guide using Bootstrap 5.3.

## Technology Stack

- **Backend**: PHP 8.0+
- **Database**: MySQL 8.0+
- **PDF Generation**: FPDF Library (Lightweight, No external dependencies)
- **Visualization**: Chart.js
- **Frontend**: HTML5, CSS3, Bootstrap 5.3, Font Awesome 6.0
- **Security**: PDO with prepared statements, bcrypt password hashing, input sanitization.

## Database Design

The system uses a normalized relational database schema designed for integrity and performance.

### Tables Structure

1. **roles** - User role definitions and access levels.
2. **programmes** - Academic programmes offered by the department.
3. **academic_sessions** - Management of academic years (Active vs. Historical).
4. **users** - System users with role assignments and account status tracking.
5. **students** - Detailed student profiles linked to programmes and sessions.
6. **dues** - Configurable fee categories mapped to academic years.
7. **payments** - Transactional records for dues payments.
8. **payment_items** - Detailed breakdown of items covered in a single payment.
9. **audit_logs** - Transparent tracking of all system changes including data snapshots.
10. **configurations** - Global system settings and metadata.

## Installation & Setup

### Prerequisites

1. XAMPP installed and running (Apache & MySQL).
2. PHP 8.0 or higher.
3. Web browser (Chrome/Edge recommended).

### Setup Instructions

1. **Clone/Download the Project**
   Place the project folder in your XAMPP `htdocs` directory:
   ```bash
   C:\xampp\htdocs\ddms\
   ```

2. **Start Services**
   Start Apache and MySQL from the XAMPP Control Panel.

3. **Database Setup**
   - Open phpMyAdmin (http://localhost/phpmyadmin).
   - Create a new database named `htu_codefest_25`.
   - Import the `database_setup.sql` file provided in the root directory.

4. **Access the Application**
   Navigate to `http://localhost/ddms/` in your browser.

## Default Login Credentials

- **Username**: `admin`
- **Password**: `admin123`
- **Role**: Administrator

## Project Structure

```
ddms/
├── assets/                 # Branding assets (COMPSSA Logos)
├── config/                # System configuration
│   ├── config.php        # App settings
│   └── database.php      # DB connection
├── css/                   # Stylesheets
├── includes/              # Shared logic
│   ├── fpdf.php          # PDF Library
│   ├── functions.php     # Core utilities
│   └── header.php        # Global UI header
├── windows/               # Application Modules
│   ├── login.php          # Auth entry
│   ├── control.php        # Dashboard & Analytics
│   ├── data.php           # Student records
│   ├── payment.php        # Payment engine
│   ├── generate_receipt.php # PDF Receipt logic
│   ├── report.php         # Analytics engine
│   ├── users.php          # User control
│   ├── audit_logs.php     # Security auditor
│   ├── settings.php       # System settings
│   └── ...                # Other modules
├── database_setup.sql     # Final schema
├── students.csv           # Sample data
└── README.md              # Documentation
```

## Security & Standards

### Audit Logging
The system implements a transparent audit trail. Every modification to critical data (Students, Payments, Sessions) logs the following:
- User identity and timestamp.
- Detailed JSON snapshots of data before and after the change.
- Technical metadata (IP address, Browser agent).

### Style Guide
The UI strictly adheres to the COMPSSA 2025 style guide:
- **Primary Colors**: Blue (#050589), Orange Brown (#FF8B00).
- **Typography**: Professional Arial-based hierarchy.
- **Branding**: Exclusive use of COMPSSA logos and department identity.

---

**Developed for HTU COMPSSA CODEFEST 2025**  
*IT Software Solutions for Business*