<?php
/**
 * Setup Script for DDMS Database
 */
require_once 'config/database.php';

// Temporarily change DB_NAME to run the setup script if the database doesn't exist
// However, the draft SQL creates the database if it doesn't exist.
// We'll use a new connection to 'mysql' to run the initial CREATE DATABASE.

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sqlFile = 'Z System Update/database draft.txt';
    if (!file_exists($sqlFile)) {
        die("SQL file not found: $sqlFile");
    }

    $sql = file_get_contents($sqlFile);
    
    // The draft SQL has some issues:
    // 1. UNIQUE KEY unique_current_session (is_current) WHERE (is_current = TRUE) - This is for PostgreSQL/SQLite, not MySQL 8.0 directly in table definition usually or needs different syntax.
    // 2. DELIMITER // is a mysql client command, not a PDO command.
    
    // I will clean up the SQL or execute it in parts.
    
    // Let's try to remove/fix the MySQL-unfriendly parts for PDO execution.
    // Actually, I'll just run it via command line if possible.
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
