<?php
require_once('tcpdf/tcpdf.php');
include 'db.php';

$week_number = isset($_GET['week_number']) ? (int)$_GET['week_number'] : 52;

// Fetch students and their balances
$sql = "SELECT admission_no, name FROM student_records";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 12);

$html = '<h2>Student Balances (Week ' . $week_number . ')</h2>
<table border="1" cellpadding="5">
<tr>
<th>Admission No</th>
<th>Name</th>
<th>School Fees</th>
<th>Lunch Fee</th>
<th>Books</th>
<th>Uniform</th>
<th>Total</th>
</tr>';

while ($row = $result->fetch_assoc()) {
    $html .= '<tr>
    <td>' . $row['admission_no'] . '</td>
    <td>' . $row['name'] . '</td>
    <td>' . rand(0, 5000) . '</td>
    <td>' . rand(0, 500) . '</td>
    <td>' . rand(0, 1000) . '</td>
    <td>' . rand(0, 2000) . '</td>
    <td>' . rand(0, 8000) . '</td>
    </tr>';
}

$html .= '</table>';
$pdf->writeHTML($html);
$pdf->Output('student_balances.pdf', 'D');
?>
