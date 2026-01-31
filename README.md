# HTU COMPSSA CODEFEST 2025 - Departmental Dues Management System

## Project Overview

This is a web-based departmental dues management system developed for HTU COMPSSA CODEFEST 2025. The system allows for managing student data, processing dues payments, and generating reports with different user roles and permissions.

## Features

- **Multi-role Authentication System**: Administrator, Supervisor, Cashier, Lecturer, and Student roles
- **Student Data Management**: Import, add, edit, and search student records
- **Payment Processing**: Track dues payments with receipt generation
- **Reporting System**: Generate various reports and analytics
- **CSV Import**: Bulk import student data from CSV files
- **Responsive Design**: Bootstrap-based UI following the project style guide

## Technology Stack

- **Backend**: PHP 8.0+
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, Bootstrap 5.3
- **Server**: Apache (XAMPP)
- **Security**: PDO with prepared statements, password hashing

## Database Design

### Tables Structure

1. **roles** - User role definitions
   - role_id, role_name, role_level, description, created_at

2. **programmes** - Academic programmes
   - programme_id, programme_code, programme_name, status, created_at

3. **academic_sessions** - Academic year sessions
   - session_id, session_name, is_current, created_at

4. **users** - Authentication and user management
   - user_id, username, password_hash, email, phone, first_name, last_name, role_id
   - login_attempts, is_locked, must_change_password, is_active
   - last_login, created_at, updated_at, deleted_at

5. **students** - student information
   - student_id, index_no, first_name, last_name, email, phone
   - programme_id, programme_level, session_type, current_academic_year, status
   - user_id, created_at, updated_at, deleted_at

6. **dues** - Configurable dues amounts
   - due_id, due_name, amount, academic_year, status, created_at

7. **payments** - Parent payment records
   - payment_id, receipt_no, student_id, academic_year, total_amount, amount_paid, balance
   - payment_date, created_by, created_at

8. **payment_items** - Line items for each payment
   - payment_item_id, payment_id, due_id, amount, academic_year, description

9. **audit_logs** - System change tracking
   - audit_id, table_name, record_id, action, details, changed_by, created_at

10. **configurations** - System settings
    - config_id, config_key, config_value, description

### Views

- **student_payment_summary** - Aggregated student payment information

## Installation & Setup

### Prerequisites

1. XAMPP installed and running
2. PHP 8.0 or higher
3. MySQL 8.0 or higher
4. Web browser

### Setup Instructions

1. **Clone/Download the Project**
   ```bash
   # Place the project in your XAMPP htdocs directory
   C:\xampp\htdocs\htu_codefest\
   ```

2. **Start XAMPP Services**
   - Start Apache and MySQL services from XAMPP Control Panel

3. **Create Database**
   ```sql
   # Open phpMyAdmin (http://localhost/phpmyadmin)
   # Import the database_setup.sql file
   # Or run the SQL commands manually
   ```

4. **Configure Database Connection**
   - Edit `config/database.php` if needed
   - Default settings work with XAMPP default configuration

5. **Import Student Data**
   - Navigate to `http://localhost/htu_codefest/`
   - Log in and use the "Data" window to import student data from `students.csv`

6. **Access the Application**
   - Open `http://localhost/htu_codefest/` in your browser

## Default Login Credentials

- **Username**: admin
- **Password**: admin123
- **Role**: Administrator

## Project Structure

```
htu_codefest/
├── assets/                 # Images and static assets
├── config/                # Database and application configuration
│   ├── config.php        # Application settings
│   └── database.php      # Database connection settings
├── css/                   # Custom CSS files
├── includes/              # Shared PHP includes and functions
│   ├── functions.php     # Helper functions
│   └── header.php        # Common page header
├── windows/               # Application windows (pages)
│   ├── login.php          # Login page
│   ├── control.php        # Main dashboard
│   ├── data.php           # Student management
│   ├── payment.php        # Payment processing
│   ├── report.php         # Reporting window
│   ├── users.php          # User management
│   ├── register.php       # Account registration
│   └── change_password.php # Password management
├── database_setup.sql     # Database schema
├── students.csv           # Sample student data
├── index.php              # Main entry point (redirects to login)
└── README.md              # This file
```

## Style Guide Compliance

The application follows the HTU Codefest 2025 style guide:

### Colors
- **Primary Orange Brown**: #FF8B00 (RGB: 255, 139, 0)
- **Primary Blue**: #050589 (RGB: 5, 5, 137)
- **Background Yellow**: #F5D200 (RGB: 245, 210, 0)
- **White**: #FFFFFF (RGB: 255, 255, 255)

### Typography
- **Font**: Arial
- **Sizes**: 10-36pt
- **Variations**: Regular, Italic, Bold

### Layout Requirements
- Header with logo and title on all pages
- Consistent alignment and whitespace
- Logical grouping of elements
- At least one non-white background element per window

## Security Features

- **SQL Injection Prevention**: PDO prepared statements
- **XSS Protection**: Input sanitization and output escaping
- **Password Security**: bcrypt hashing
- **Session Management**: Secure session handling
- **Role-based Access Control**: Permission-based navigation

## User Roles & Permissions

1. **Administrator** (Level 5)
   - Full system access
   - User management
   - System configuration

2. **Supervisor** (Level 4)
   - View reports
   - Manage students
   - Payment oversight

3. **Cashier** (Level 3)
   - Process payments
   - View student data
   - Generate receipts

4. **Lecturer** (Level 2)
   - View student data
   - Access reports
   - Limited editing

5. **Student** (Level 1)
   - View own data
   - Payment history
   - Profile management

## API Endpoints

The system provides RESTful endpoints for:
- User authentication
- Student data management
- Payment processing
- Report generation

## Error Handling

- Comprehensive exception handling
- User-friendly error messages
- Detailed logging for debugging
- Graceful degradation

## Performance Optimization

- Database indexing on frequently queried fields
- Prepared statements for query optimization
- Efficient data pagination
- Caching strategies

## Testing

- Unit tests for core functions
- Integration tests for database operations
- User acceptance testing scenarios
- Security testing protocols

## Deployment

### Production Checklist
- [ ] Disable error reporting
- [ ] Configure secure database credentials
- [ ] Set up SSL certificate
- [ ] Configure backup procedures
- [ ] Set up monitoring and logging

## Support & Documentation

For technical support or questions:
- Review the inline code documentation
- Check the database schema documentation
- Refer to the style guide for UI/UX questions

## License

This project is developed for HTU COMPSSA CODEFEST 2025 educational purposes.

---

**Developed for HTU COMPSSA CODEFEST 2025**  
*IT Software Solutions for Business* 