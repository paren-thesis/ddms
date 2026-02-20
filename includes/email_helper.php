<?php
/**
 * HTU COMPSSA DDMS - Email Helper
 * Wrapper functions for PHPMailer
 */

require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../libs/PHPMailer-6.9.3/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer-6.9.3/src/SMTP.php';
require_once __DIR__ . '/../libs/PHPMailer-6.9.3/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using PHPMailer
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body HTML email body
 * @param string $altBody Plain text alternative
 * @return array ['success' => bool, 'message' => string]
 */
function sendEmail($to, $subject, $body, $altBody = '') {
    if (!MAIL_ENABLED) {
        return [
            'success' => false,
            'message' => 'Email sending is disabled. Configure config/email.php to enable.'
        ];
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;
        
        // Sender & recipient
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully.'];
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return ['success' => false, 'message' => 'Email failed: ' . $mail->ErrorInfo];
    }
}

/**
 * Send a password reset notification email
 * 
 * @param string $to Recipient email
 * @param string $username The user's username
 * @param string $newPassword The newly generated password
 * @return array ['success' => bool, 'message' => string]
 */
function sendPasswordResetEmail($to, $username, $newPassword) {
    $subject = 'Your Password Has Been Reset - ' . APP_NAME;
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background-color: #050589; padding: 20px; text-align: center;'>
            <h1 style='color: #FFFFFF; margin: 0;'>HTU DDMS</h1>
            <p style='color: #F5D200; margin: 5px 0 0;'>Departmental Dues Management System</p>
        </div>
        <div style='padding: 30px; background-color: #f8f9fa;'>
            <h2 style='color: #333;'>Password Reset</h2>
            <p>Hello,</p>
            <p>Your password for the HTU Departmental Dues Management System has been reset by an administrator.</p>
            <div style='background-color: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;'>
                <p style='margin: 5px 0; color: #666;'>Username</p>
                <p style='margin: 5px 0; font-size: 18px; font-weight: bold; color: #050589;'>$username</p>
                <hr style='border: none; border-top: 1px solid #eee; margin: 15px 0;'>
                <p style='margin: 5px 0; color: #666;'>New Password</p>
                <p style='margin: 5px 0; font-size: 18px; font-weight: bold; color: #FF8B00;'>$newPassword</p>
            </div>
            <p style='color: #dc3545; font-weight: bold;'>⚠️ Please change your password immediately after logging in.</p>
            <p style='color: #666; font-size: 13px;'>If you did not request this password reset, please contact the administrator immediately.</p>
        </div>
        <div style='background-color: #333; padding: 15px; text-align: center;'>
            <p style='color: #999; font-size: 12px; margin: 0;'>© " . date('Y') . " HTU COMPSSA — Departmental Dues Management System</p>
        </div>
    </div>";
    
    $altBody = "Your password has been reset.\nUsername: $username\nNew Password: $newPassword\nPlease change your password after logging in.";
    
    return sendEmail($to, $subject, $body, $altBody);
}

/**
 * Send a payment receipt confirmation email
 * 
 * @param string $to Recipient email
 * @param array $details Payment details array
 * @return array ['success' => bool, 'message' => string]
 */
function sendPaymentReceiptEmail($to, $details) {
    $subject = 'Payment Confirmation - Receipt #' . $details['receipt_no'];
    
    $studentName = htmlspecialchars($details['student_name']);
    $indexNo = htmlspecialchars($details['index_no']);
    $receiptNo = htmlspecialchars($details['receipt_no']);
    $dueName = htmlspecialchars($details['due_name']);
    $amountPaid = number_format($details['amount_paid'], 2);
    $totalDue = number_format($details['total_due'], 2);
    $balance = number_format($details['balance'], 2);
    $paymentDate = date('d M, Y', strtotime($details['payment_date']));
    $academicYear = htmlspecialchars($details['academic_year']);
    $balanceColor = $details['balance'] > 0 ? '#dc3545' : '#198754';
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background-color: #050589; padding: 20px; text-align: center;'>
            <h1 style='color: #FFFFFF; margin: 0;'>HTU DDMS</h1>
            <p style='color: #F5D200; margin: 5px 0 0;'>Payment Confirmation</p>
        </div>
        <div style='padding: 30px; background-color: #f8f9fa;'>
            <h2 style='color: #333; margin-top: 0;'>Payment Received ✅</h2>
            <p>Dear <strong>$studentName</strong>,</p>
            <p>Your payment has been successfully processed. Here are the details:</p>
            
            <div style='background-color: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 25px; margin: 20px 0;'>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 8px 0; color: #666; border-bottom: 1px solid #eee;'>Receipt No.</td>
                        <td style='padding: 8px 0; text-align: right; font-weight: bold; color: #050589; border-bottom: 1px solid #eee;'>$receiptNo</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #666; border-bottom: 1px solid #eee;'>Index Number</td>
                        <td style='padding: 8px 0; text-align: right; font-weight: bold; border-bottom: 1px solid #eee;'>$indexNo</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #666; border-bottom: 1px solid #eee;'>Due Category</td>
                        <td style='padding: 8px 0; text-align: right; border-bottom: 1px solid #eee;'>$dueName</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #666; border-bottom: 1px solid #eee;'>Academic Year</td>
                        <td style='padding: 8px 0; text-align: right; border-bottom: 1px solid #eee;'>$academicYear</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #666; border-bottom: 1px solid #eee;'>Payment Date</td>
                        <td style='padding: 8px 0; text-align: right; border-bottom: 1px solid #eee;'>$paymentDate</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #666; border-bottom: 1px solid #eee;'>Total Due</td>
                        <td style='padding: 8px 0; text-align: right; border-bottom: 1px solid #eee;'>GH₵ $totalDue</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; color: #198754; font-weight: bold; font-size: 16px;'>Amount Paid</td>
                        <td style='padding: 10px 0; text-align: right; color: #198754; font-weight: bold; font-size: 16px;'>GH₵ $amountPaid</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold; color: $balanceColor;'>Balance</td>
                        <td style='padding: 8px 0; text-align: right; font-weight: bold; color: $balanceColor;'>GH₵ $balance</td>
                    </tr>
                </table>
            </div>
            
            <p style='color: #666; font-size: 13px;'>This is an automated confirmation. Please keep this email for your records.</p>
        </div>
        <div style='background-color: #333; padding: 15px; text-align: center;'>
            <p style='color: #999; font-size: 12px; margin: 0;'>© " . date('Y') . " HTU COMPSSA — Departmental Dues Management System</p>
        </div>
    </div>";
    
    $altBody = "Payment Confirmation - Receipt #$receiptNo\nStudent: $studentName ($indexNo)\nDue: $dueName | Academic Year: $academicYear\nAmount Paid: GHS $amountPaid | Balance: GHS $balance\nDate: $paymentDate";
    
    return sendEmail($to, $subject, $body, $altBody);
}
?>
