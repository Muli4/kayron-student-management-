give html code for this <?php
session_start();

include 'db.php'; // Include database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = trim($_POST['admission_no'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_type = $_POST['payment_type'] ?? 'Cash';
    $receipt_number = trim($_POST['receipt_number'] ?? '');

    if (empty($admission_no) || $amount <= 0 || empty($receipt_number)) {
        echo json_encode(["error" => "Invalid input data!"]);
        exit();
    }

    // Try to fetch student details from current students
    $stmt = $conn->prepare("SELECT name, class FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    if (!$stmt->execute()) {
        echo json_encode(["error" => "SQL Error: " . $stmt->error]);
        exit();
    }
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    // If not found, check graduated students
    if (!$student) {
        $stmt = $conn->prepare("SELECT name, class_completed FROM graduated_students WHERE admission_no = ?");
        $stmt->bind_param("s", $admission_no);
        if (!$stmt->execute()) {
            echo json_encode(["error" => "SQL Error: " . $stmt->error]);
            exit();
        }
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();
    }

    if (!$student) {
        echo json_encode(["error" => "Admission number not found in records!"]);
        exit();
    }

    $name = $student['name'];
    $class = $student['class'] ?? $student['class_completed'];

    // Fetch school fees record
    $stmt = $conn->prepare("SELECT total_fee, amount_paid, balance FROM school_fees WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    if (!$stmt->execute()) {
        echo json_encode(["error" => "SQL Error: " . $stmt->error]);
        exit();
    }
    $result = $stmt->get_result();
    $fee_record = $result->fetch_assoc();
    $stmt->close();

    if (!$fee_record) {
        echo json_encode(["error" => "No school fee record found!"]);
        exit();
    }

    $total_fee = $fee_record['total_fee'];
    $previous_paid = $fee_record['amount_paid'];
    $previous_balance = $fee_record['balance'];

    // Calculate new payment and balance (allow negative for overpayment)
    $new_total_paid = $previous_paid + $amount;
    $new_balance = $total_fee - $new_total_paid;

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Update school_fees
        $stmt = $conn->prepare("UPDATE school_fees SET amount_paid = ?, balance = ? WHERE admission_no = ?");
        $stmt->bind_param("dds", $new_total_paid, $new_balance, $admission_no);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();

        // Insert into transactions
        $stmt = $conn->prepare("INSERT INTO school_fee_transactions (name, admission_no, class, amount_paid, receipt_number, payment_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdss", $name, $admission_no, $class, $amount, $receipt_number, $payment_type);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();

        // Commit transaction
        $conn->commit();

        echo json_encode([
            "success" => true,
            "message" => "School Fee Payment Successful!",
            "new_balance" => $new_balance
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(["error" => "Transaction failed: " . $e->getMessage()]);
    }

    $conn->close();
    exit();
}
?>
