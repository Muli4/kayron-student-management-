<?php
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $payments = json_decode($_POST['payments'], true);

    if (!$payments || !is_array($payments)) {
        echo json_encode(["status" => "error", "message" => "Invalid payment data."]);
        exit;
    }

    // Start Transaction
    $conn->begin_transaction();
    try {
        foreach ($payments as $payment) {
            $stmt = $conn->prepare("INSERT INTO payments (admission_no, payment_type, fee_type, amount, receipt_number) 
                                    VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssis", $payment['admission_no'], $payment['payment_type'], $payment['fee_type'], $payment['amount'], $payment['receipt_number']);

            if (!$stmt->execute()) {
                throw new Exception("Payment for " . $payment['fee_type'] . " failed.");
            }
        }

        // Commit Transaction
        $conn->commit();
        echo json_encode(["status" => "success", "message" => "✅ Payment successful!"]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "❌ Payment failed: " . $e->getMessage()]);
    }

    $conn->close();
}
?>
