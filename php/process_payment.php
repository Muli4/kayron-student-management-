<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = $_POST['admission_no'];
    $payment_type = $_POST['payment_type'];
    $fees = $_POST['fees'];
    $receipt_number = "REC" . date("Ymd") . strtoupper(substr(md5(rand()), 0, 5));

    $total_amount = 0;
    $fee_details = [];

    foreach ($fees as $fee) {
        $amount_key = "amount_" . $fee;
        if (isset($_POST[$amount_key]) && is_numeric($_POST[$amount_key])) {
            $amount = $_POST[$amount_key];
            $total_amount += $amount;
            $fee_details[$fee] = $amount;
        }
    }

    // Store payment details in the database (Mock example)
    // Redirect to receipt page with details
    $query_string = http_build_query([
        "receipt_number" => $receipt_number,
        "admission_no" => $admission_no,
        "payment_type" => $payment_type,
        "fees" => json_encode($fee_details),
        "total" => $total_amount
    ]);

    header("Location: receipt.php?$query_string");
    exit();
}
?>
