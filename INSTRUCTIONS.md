# HTU Departmental Dues Management System (DDMS)
**Comprehensive Setup & Usage Guide**

---

## 1. Prerequisites

- **XAMPP** (PHP 8.0+ and MySQL 8.0+)
- A modern web browser (Chrome, Edge, or Firefox)
- The project folder (`ddms`) located in your XAMPP `htdocs` directory.

---

## 2. Project Structure

```
ddms/
├── assets/                 # Logo and Branding (COMPSSA)
├── config/                # Database configuration
├── css/                   # Global styles
├── includes/              # Core functions and PDF library
├── windows/               # Application Modules (Pages)
│   ├── control.php        # Dashboard & Analytics
│   ├── data.php           # Student management
│   ├── payment.php        # Payment & Receipt links
│   ├── generate_receipt.php # Dual-copy A4 generator
│   ├── audit_logs.php     # System Auditor
│   ├── settings.php       # Sessions & Dues control
│   └── ...
├── database_setup.sql     # Database schema
└── README.md              # Project summary
```

---

## 3. Installation & Setup

### **A. Repository Placement**

1. Move the `ddms` folder to:
   ```
   C:\xampp\htdocs\ddms
   ```

### **B. Start XAMPP Services**

1. Open the XAMPP Control Panel.
2. Start **Apache** and **MySQL**.

### **C. Database Initialization**

1. Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
2. Create a database named `htu_codefest_25`.
3. Select the database and click the **Import** tab.
4. Choose `database_setup.sql` from the root of the project.
5. Click **Import/Go**.

---

## 4. Usage Guide

### **A. Default Login**
- **Username:** `admin`
- **Password:** `admin123`

### **B. Managing Sessions (First Step)**
Go to **Dues & Sessions** (Settings) to:
1. Ensure the correct **Academic Year** is set as "Current".
2. Add or Edit **Dues Categories** (e.g., Departmental Dues, Lab Fees).

### **C. Processing Payments**
1. Search for a student in the **Payment** window.
2. Select the dues items being paid.
3. Click **Add Payment**.
4. Click **Print Receipt** (PDF) to generate a dual-copy A4 receipt.

### **D. Audit & Security**
Use the **Audit Logs** window to track all changes. The system captures:
- Who made the change.
- The exact data before and after the modification.
- Time and technical metadata.

---

## 5. Printing Receipts

The system generates a high-fidelity **A4 portrait PDF**:
- **Top Half**: Student Copy.
- **Bottom Half**: Department Copy.
- **Divider**: A dashed line for easy cutting.
- **Branding**: Official COMPSSA branding with automated "Amount in Words" conversion.

---

## 6. Troubleshooting

- **PDF Not Loading**: Ensure the `includes/fpdf.php` file exists and there are no PHP warnings being output.
- **Database Error**: Check `config/database.php` for correct credentials.
- **Incorrect Logo**: Clear your browser cache to see the new `compssa_logo.png`.

---

**Enjoy using the DDMS - Built for COMPSSA 2025**