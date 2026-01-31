<?php
ob_start();
/**
 * HTU COMPSSA CODEFEST 2025 - PDF Receipt Generator
 * Generates an official receipt modeled after the GCB format
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/fpdf.php';

// Check login
if (!isLoggedIn()) {
    die("Unauthorized access.");
}

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$payment_id) {
    die("Invalid payment ID.");
}

try {
    $pdo = getDBConnection();
    
    // Fetch detailed payment info
    $sql = "SELECT p.*, s.index_no, s.first_name, s.last_name, s.programme_level, prog.programme_name, u.first_name as processed_by_first, u.last_name as processed_by_last
            FROM payments p
            JOIN students s ON p.student_id = s.student_id
            JOIN programmes prog ON s.programme_id = prog.programme_id
            JOIN users u ON p.created_by = u.user_id
            WHERE p.payment_id = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        die("Payment record not found.");
    }
    
    // Fetch items (optional for receipt detail)
    $stmt = $pdo->prepare("SELECT pi.*, d.due_name FROM payment_items pi JOIN dues d ON pi.due_id = d.due_id WHERE pi.payment_id = ?");
    $stmt->execute([$payment_id]);
    $items = $stmt->fetchAll();

    // Initialize FPDF
    $pdf = new FPDF('P', 'mm', array(210, 148)); // A5 landscape-ish or portrait cut
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 10);

    // --- Header ---
    // COMPSSA Logo
    if (file_exists('../assets/format/Compssa Logo.png')) {
        // Center the logo (A5 width is 148mm, logo is 40mm, so x = (148-40)/2 = 54mm)
        $pdf->Image('../assets/format/Compssa Logo.png', 54, 10, 40);
    }
    $pdf->Ln(25);

    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 5, 'HO TECHNICAL UNIVERSITY', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, 'COMPUTER SCIENCE STUDENTS ASSOCIATION (COMPSSA)', 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'OFFICIAL RECEIPT', 0, 1, 'C');
    
    $pdf->Line(10, 40, 138, 40);
    $pdf->Ln(5);

    // --- Section 1: Transaction Details ---
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(35, 6, 'Transaction Ref:', 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(45, 6, $payment['receipt_no'], 0, 0);
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(30, 6, 'Date:', 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 6, date('d-M-Y', strtotime($payment['payment_date'])), 0, 1);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(35, 6, 'Student ID:', 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(45, 6, $payment['index_no'], 0, 1);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(35, 6, 'Student Name:', 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(45, 6, strtoupper($payment['first_name'] . ' ' . $payment['last_name']), 0, 1);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(35, 6, 'Programme:', 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 6, $payment['programme_name'], 0, 'L');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(35, 6, 'Academic Year:', 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(45, 6, $payment['academic_year'], 0, 0);
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(30, 6, 'Level:', 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 6, $payment['programme_level'], 0, 1);

    $pdf->Ln(2);
    $pdf->Line(10, $pdf->GetY(), 138, $pdf->GetY());
    $pdf->Ln(2);

    // --- Section 2: Financials ---
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 8, 'Amount Paid:', 0, 0);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(45, 8, formatCurrency($payment['amount_paid']), 0, 1);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(35, 6, 'Amount in Words:', 0, 0);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->MultiCell(0, 6, numberToWords($payment['amount_paid']), 0, 'L');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(35, 6, 'Description:', 0, 0);
    $description = "Payment for ";
    $desc_items = [];
    foreach ($items as $item) $desc_items[] = $item['due_name'];
    $description .= implode(", ", $desc_items);
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, $description, 0, 'L');

    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(35, 6, 'Served By:', 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 6, $payment['processed_by_first'] . ' ' . $payment['processed_by_last'], 0, 1);

    // --- Footer ---
    $pdf->SetY(-30);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Line(10, $pdf->GetY(), 138, $pdf->GetY());
    $pdf->MultiCell(0, 4, "This receipt was issued with the mandate of Ho Technical University COMPSSA. It is a computer-generated document and does not require a physical signature unless otherwise stated.", 0, 'C');
    $pdf->Cell(0, 4, "Verify at: http://htu.edu.gh/portal", 0, 1, 'C');

    // Output PDF
    $pdf->Output('I', 'Receipt_' . $payment['receipt_no'] . '.pdf');

} catch (Exception $e) {
    die("Error generating receipt: " . $e->getMessage());
}
?>
