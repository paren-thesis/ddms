# CSV Import Guide - DDMS

To successfully import data into the Departmental Dues Management System, your CSV file must follow the structure below.

## Field Specifications

| # | Column Name | Format / Example | Required | Default if Empty |
|:--|:------------|:-----------------|:--------:|:-----------------|
| 0 | **Name** | `Lastname, Firstname` | **Yes** | - |
| 1 | **Index No** | `012345678` | **Yes** | (Used as Username) |
| 2 | Program Level| `100`, `200`, `300`, `400`, `Top-Up` | No | `100` |
| 3 | Session | `Regular`, `Weekend` | No | `Regular` |
| 4 | **Programme** | `BTech Computer Science` | **Yes** | - |
| 5 | Password | `mypassword123` | No | `Index No` |
| 6 | Phone | `0241234567` | No | - |
| 7 | **Academic Year**| `2024/2025` | **Yes** | - |
| 8 | Dues Paid | `50.00` | No | `0` |
| 9 | Receipt No | `REC-101` | Conditional* | - |
| 10| Payment Date | `DD.MM.YYYY` (e.g., `05.02.2025`) | No | System Date |
| 11| Position | `student`, `admin`, `cashier`, `hod` | No | `student` |
| 12| Status | `Active`, `Inactive`, `Graduated` | No | `Active` |
| 13| Email | `student@example.com` | No | `index@htu.edu.gh` |

> [!IMPORTANT]
> **Receipt No** is mandatory if **Dues Paid** is greater than 0.
> **Email** is now optional; if left empty, the system automatically generates `indexnumber@htu.edu.gh`.

## Validation & Auto-Correction
1. **Leading Zeros**: If Excel strips leading zeros from **Index No** or **Phone**, the system will automatically attempt to restore them if the value appears numeric.
2. **Name Format**: Must be `Lastname, Firstname` (comma separated).
3. **Email**: Auto-generated if missing. Must be a valid format if provided.
4. **Phone**: If provided, must be a valid 10-15 digit number.
5. **Dates**: Use the format `DD.MM.YYYY` (dot separated).
6. **Line Endings**: The system now supports both `\r\n` (Windows) and `\r` (Mac) row separators.

## Sample Data (Ready in required_format.csv)
I have populated [required_format.csv](file:///c:/xampp/htdocs/ddms/required_format.csv) with sample records demonstrating these rules.
