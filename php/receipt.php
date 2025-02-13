<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "school_database");

if ($conn->connect_error) {
    die("Database connection failed.");
}

// If the form is submitted, process the purchase
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = trim($_POST['admission_no']);
    $receipt_no = trim($_POST['receipt_number']);
    $selected_books = $_POST['books'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $amounts_paid = $_POST['amounts_paid'] ?? [];
    $payment_type = $_POST['payment_type'];

    if (empty($selected_books)) {
        die("Please select at least one book.");
    }

    // Fetch student details
    $stmt = $conn->prepare("SELECT name FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $student_result = $stmt->get_result();

    if ($student_result->num_rows === 0) {
        die("Invalid admission number.");
    }

    $student = $student_result->fetch_assoc();
    $student_name = $student['name'];

    // Store purchase details
    $conn->begin_transaction();
    $total_amount = 0;
    $_SESSION['receipt_books'] = [];

    $book_stmt = $conn->prepare("SELECT book_name, price FROM book_prices WHERE book_id = ?");
    $insert_stmt = $conn->prepare("INSERT INTO book_purchases (receipt_number, admission_no, name, book_name, quantity, total_price, amount_paid, balance, payment_type) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($selected_books as $index => $book_id) {
        $quantity = (int)$quantities[$index];

        $book_stmt->bind_param("i", $book_id);
        $book_stmt->execute();
        $book_result = $book_stmt->get_result();

        if ($book_result->num_rows > 0) {
            $book = $book_result->fetch_assoc();
            $book_name = $book['book_name'];
            $price = $book['price'];
            $item_total = $price * $quantity;
            $amount_paid = isset($amounts_paid[$index]) ? floatval($amounts_paid[$index]) : 0;
            $balance = $item_total - $amount_paid;
            $total_amount += $amount_paid;

            $insert_stmt->bind_param("ssssiddds", $receipt_no, $admission_no, $student_name, $book_name, $quantity, $item_total, $amount_paid, $balance, $payment_type);
            $insert_stmt->execute();

            $_SESSION['receipt_books'][] = ['name' => $book_name, 'quantity' => $quantity, 'amount_paid' => $amount_paid];
        }
    }

    $conn->commit();

    header("Location: receipt.php?receipt_number=$receipt_no&admission_no=$admission_no&payment_type=$payment_type&total=$total_amount");
    exit();
}

// If receipt is requested, generate it
if (isset($_GET['receipt_number'], $_GET['admission_no'], $_GET['payment_type'])) {
    $receipt_number = htmlspecialchars($_GET['receipt_number']);
    $admission_no = htmlspecialchars($_GET['admission_no']);
    $payment_type = htmlspecialchars($_GET['payment_type']);
    $total = isset($_GET['total']) ? (float) $_GET['total'] : 0.00;

    // Fetch student details
    $student_query = "SELECT name FROM student_records WHERE admission_no = ?";
    $stmt = $conn->prepare($student_query);
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $student_result = $stmt->get_result();
    $student_name = $student_result->num_rows > 0 ? htmlspecialchars($student_result->fetch_assoc()['name']) : "Unknown";
    $stmt->close();

    $books = $_SESSION['receipt_books'] ?? [];
    $date = date("d-m-Y");
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Receipt</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 14px; text-align: center; width: 300px; margin: auto; }
            .receipt { border: 1px dashed black; padding: 10px; }
            .title { font-weight: bold; text-transform: uppercase; }
            .line { border-top: 1px dashed black; margin: 5px 0; }
            .amount { text-align: right; }
            .total { font-weight: bold; }
            .print-btn { margin-top: 15px; padding: 8px 15px; background: green; color: white; border: none; cursor: pointer; }
        </style>
    </head>
    <body onload="window.print()">

    <div class="receipt">
        <div class="title">Kayron Junior School</div>
        <small>Tel: 0703373151 / 0740047243</small>
        <div class="line"></div>
        <div class="line"></div>
        <strong>Official Receipt</strong><br>
        Date: <strong><?php echo $date; ?></strong><br>
        Receipt No: <strong><?php echo $receipt_number; ?></strong><br>
        Admission No: <strong><?php echo $admission_no; ?></strong><br>
        Student Name: <strong><?php echo $student_name; ?></strong><br>
        Payment Method: <strong><?php echo ucfirst($payment_type); ?></strong>
        <div class="line"></div>
        <div class="line"></div>

        <table width="100%">
            <tr>
                <th>Book</th>
                <th>Qty</th>
                <th>Amount Paid</th>
            </tr>
            <?php foreach ($books as $book): ?>
                <tr>
                    <td><?php echo htmlspecialchars($book['name']); ?></td>
                    <td><?php echo htmlspecialchars($book['quantity']); ?></td>
                    <td class="amount">KES <?php echo number_format($book['amount_paid'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <div class="line"></div>
        <div class="line"></div>
        <div class="total">TOTAL: KES <?php echo number_format($total, 2); ?></div>
    </div>

    </body>
    </html>
    <?php
}
?>
