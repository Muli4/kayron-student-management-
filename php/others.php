<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1); // Show errors during testing
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/errors.log'); // Log errors to file

include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize form inputs
    $admission_no   = trim($_POST['admission_no'] ?? '');
    $fee_type       = ucfirst(strtolower(trim($_POST['fee_type'] ?? ''))); // Normalize e.g. "Admission"
    $amount         = floatval($_POST['amount'] ?? 0);
    $payment_type   = ucfirst(strtolower(trim($_POST['payment_type'] ?? 'Cash')));
    $receipt_number = trim($_POST['receipt_number'] ?? '');

    // Validate inputs
    if (empty($admission_no) || empty($fee_type) || $amount <= 0 || empty($receipt_number)) {
        die("Invalid payment data.");
    }

    // 1️⃣ Retrieve student name and term
    $stmt = $conn->prepare("SELECT name, term FROM student_records WHERE admission_no = ?");
    if (!$stmt) {
        die("MySQL Error (Fetching student): " . $conn->error);
    }
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $stmt->bind_result($name, $term);
    $stmt->fetch();
    $stmt->close();

    if (empty($name)) {
        die("Student record not found.");
    }
    if (!$term) {
        $term = "Unknown";
    }

    // 2️⃣ Check if admission fee has already been paid and if amount is less than 1000
    if ($fee_type === "Admission") {
        $stmt = $conn->prepare("
            SELECT id, amount 
            FROM others 
            WHERE admission_no = ? 
              AND LOWER(fee_type) = 'admission'
            LIMIT 1
        ");
        if (!$stmt) {
            die("MySQL Error (Checking admission fee): " . $conn->error);
        }
        $stmt->bind_param("s", $admission_no);
        $stmt->execute();
        $stmt->bind_result($existing_id, $existing_amount);
        $stmt->fetch();
        $stmt->close();

        if ($existing_amount !== null) {
            if ($existing_amount < 1000) {
                // Update the existing amount by adding new amount
                $new_amount = $existing_amount + $amount;
                $update_stmt = $conn->prepare("
                    UPDATE others 
                    SET amount = ? 
                    WHERE id = ?
                ");
                if (!$update_stmt) {
                    die("MySQL Error (Updating admission fee): " . $conn->error);
                }
                $update_stmt->bind_param("di", $new_amount, $existing_id);
                if ($update_stmt->execute()) {
                    $update_stmt->close();

                    // Also insert into other_transactions table for payment history
                    $insert_transaction = $conn->prepare("
                        INSERT INTO other_transactions (others_id, amount, payment_type, receipt_number)
                        VALUES (?, ?, ?, ?)
                    ");
                    if (!$insert_transaction) {
                        die("MySQL Error (Inserting transaction): " . $conn->error);
                    }
                    $insert_transaction->bind_param("idss", $existing_id, $amount, $payment_type, $receipt_number);
                    if ($insert_transaction->execute()) {
                        echo "✅ Admission fee updated successfully!";
                    } else {
                        die("Error saving transaction: " . $insert_transaction->error);
                    }
                    $insert_transaction->close();

                    exit; // Stop further insertion, update done
                } else {
                    die("Error updating admission fee: " . $update_stmt->error);
                }
            } else {
                // If admission fee >= 1000, block new payment
                die("Admission fee has already been paid and is 1000 or more.");
            }
        }
    }

    // 3️⃣ Insert into `others` table (for all other cases, or new admission fee)
    $stmt = $conn->prepare("
        INSERT INTO others (receipt_number, admission_no, name, term, fee_type, amount, payment_type) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        die("MySQL Error (Inserting into others): " . $conn->error);
    }

    // sssssds => 5 strings, 1 double, 1 string
    $stmt->bind_param("sssssds", 
        $receipt_number, 
        $admission_no, 
        $name, 
        $term, 
        $fee_type, 
        $amount, 
        $payment_type
    );

    if ($stmt->execute()) {
        $others_id = $stmt->insert_id; // Get the ID for transaction link
        $stmt->close();

        // 4️⃣ Insert into `other_transactions` (payment history)
        $stmt2 = $conn->prepare("
            INSERT INTO other_transactions (others_id, amount, payment_type, receipt_number) 
            VALUES (?, ?, ?, ?)
        ");
        if (!$stmt2) {
            die("MySQL Error (Inserting transaction): " . $conn->error);
        }

        $stmt2->bind_param("idss", $others_id, $amount, $payment_type, $receipt_number);

        if ($stmt2->execute()) {
            echo "✅ Payment successfully recorded in both tables!";
        } else {
            die("Error saving transaction: " . $stmt2->error);
        }

        $stmt2->close();

    } else {
        die("Error saving payment: " . $stmt->error);
    }
}

$conn->close();
?>
