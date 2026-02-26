# Audit Logs: Antithesis Analysis

The DDMS currently features an audit log system implemented across `audit_logs.php` and the `logActivity` function in `functions.php`. Here is an analysis of its current state and flaws.

## The Ideal State
An audit log acts as the financial and operational memory of the system. It should:
1. Irrefutably record **who** did **what**, **when**, and **where**.
2. Capture before-and-after states for critical changes (e.g., editing a student's index number or modifying a payment).
3. Be completely immutable (no user, not even an admin, should be able to edit or delete a log).
4. Provide excellent searchability and filtering to investigate anomalies.

## Technical Constraints and Flaws (The Antithesis)

### 1. Inconsistent Data Capture
- **The Bug:** The core logging function (`logActivity` in `functions.php`, line 231) accepts `old_values` and `new_values` as optional parameters. However, in practice, throughout the codebase (e.g., in `users.php` and `data.php`), these parameters are rarely populated correctly with the complete state of the record before and after the change. 
- **Impact:** When a user is edited, the log only records `['username' => $username]`. It completely fails to capture what the user's data looked like *before* the edit. If an admin maliciously changes a student's dues balance or index number, the audit log will show *that* a change occurred, but it will be impossible to see *what* the previous value was to restore it.

### 2. Missing Context on Critical Actions
- **The Bug:** During the CSV import process, the `logActivity` function is called once at the very end (`logActivity('CSV_IMPORT', 'students', null, null, ['new' => ..., 'updated' => ...])`). 
- **Impact:** It does not log the individual changes made to the "updated" students. If 50 students are updated during an import, there is zero record of what specific fields were overwritten for each student. This makes bulk data corruption unrecoverable.

### 3. Lack of Database-Level Immutability
- **Design Flaw:** The `audit_logs` table (`database_setup.sql`) has no database-level triggers or strict permissions preventing `UPDATE` or `DELETE` statements.
- **Impact:** If a malicious actor compromises an admin account or gains direct database access, they can simply run `DELETE FROM audit_logs WHERE user_id = X` to cover their tracks. A professional financial system requires append-only constraints (often via triggers or read-only database user configurations for the application layer) to guarantee log integrity.

### 4. Poor Error Handling in Logging
- **The Bug:** If the `logActivity` query fails (e.g., due to a database connection hiccup), it simply returns `false` and writes to the PHP error log (`functions.php`, line 254). 
- **Impact:** The main transaction (like a payment or user deletion) will still successfully commit, but the audit trail will be silently lost. In a financial system, if the audit log fails to write, the transaction itself should be rolled back.

### Summary
While the foundation for an audit log exists, it is currently "security theater." It records that actions happen, but fails to capture the granular *before-and-after* data required to actually audit or undo a malicious change.
