<?php
/**
 * HTU COMPSSA DDMS - Email Configuration
 * PHPMailer SMTP settings
 * 
 * Update these settings with your actual email credentials.
 * For Gmail: Enable "App Passwords" in your Google Account security settings.
 */

// SMTP Configuration
define('MAIL_HOST', 'smtp.gmail.com');          // SMTP server (Gmail default)
define('MAIL_PORT', 587);                        // SMTP port (587 for TLS)
define('MAIL_USERNAME', 'your-email@gmail.com'); // Your email address
define('MAIL_PASSWORD', 'your-app-password');     // App password (NOT your regular password)
define('MAIL_ENCRYPTION', 'tls');                // Encryption: 'tls' or 'ssl'
define('MAIL_FROM_ADDRESS', 'your-email@gmail.com'); // Sender email
define('MAIL_FROM_NAME', 'HTU DDMS Admin');       // Sender name

// Email feature toggle - set to true once credentials are configured
define('MAIL_ENABLED', true);
?>
