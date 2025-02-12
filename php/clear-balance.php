<?php
session_start();
$conn = new mysqli("localhost", "root", "", "school_database");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if admission number is provided
$admission_no = $_GET['admission_no'] ?? '';

if (!$admission_no) {
    $_SESSION['error'] = "Please enter an admission number.";
    header("Location: clear-balance.php");
    exit();
}

// Fetch outstanding balances for the student
$query = "SELECT id, book_name, quantity, total_price, amount_paid, balance FROM book_purchases WHERE admission_no = ? AND balance > 0";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $admission_no);
$stmt->execute();
$result = $stmt->get_result();
$books = $result->fetch_all(MYSQLI_ASSOC);

// Handle payment update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    foreach ($_POST['payments'] as $purchase_id => $payment) {
        $payment = floatval($payment);
        
        // Get current balance
        $balance_query = "SELECT balance, amount_paid FROM book_purchases WHERE id = ?";
        $stmt = $conn->prepare($balance_query);
        $stmt->bind_param("i", $purchase_id);
        $stmt->execute();
        $balance_result = $stmt->get_result()->fetch_assoc();

        if ($balance_result) {
            $new_amount_paid = $balance_result['amount_paid'] + $payment;
            $new_balance = $balance_result['balance'] - $payment;

            if ($new_balance < 0) {
                $_SESSION['error'] = "Overpayment detected! Please enter a correct amount.";
                header("Location: clear-balance.php?admission_no=" . $admission_no);
                exit();
            }

            // Update the database
            $update_query = "UPDATE book_purchases SET amount_paid = ?, balance = ?, purchase_date = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ddi", $new_amount_paid, $new_balance, $purchase_id);
            $stmt->execute();
        }
    }

    $_SESSION['success'] = "Balance cleared successfully!";
    header("Location: clear-balance.php?admission_no=" . $admission_no);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clear Balance</title>
    <link rel="stylesheet" href="../style/style.css">
</head>
<body>

<div class="heading-all">
    <h2 class="title">Clear Outstanding Balance</h2>
</div>

<div class="lunch-form">
    <div class="add-heading">
        <h2>Balance Details for Admission No: <?= htmlspecialchars($admission_no) ?></h2>
    </div>

    <?php
    if (isset($_SESSION['success'])) {
        echo "<p class='message success'>" . $_SESSION['success'] . "</p>";
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        echo "<p class='message error'>" . $_SESSION['error'] . "</p>";
        unset($_SESSION['error']);
    }

    if (empty($books)) {
        echo "<p class='message info'>No outstanding balances for this student.</p>";
    } else {
    ?>

    <form action="" method="POST">
        <table border="1">
            <tr>
                <th>Book Name</th>
                <th>Quantity</th>
                <th>Total Price (KES)</th>
                <th>Amount Paid (KES)</th>
                <th>Balance (KES)</th>
                <th>Payment (KES)</th>
            </tr>

            <?php foreach ($books as $book) { ?>
                <tr>
                    <td><?= htmlspecialchars($book['book_name']) ?></td>
                    <td><?= $book['quantity'] ?></td>
                    <td><?= number_format($book['total_price'], 2) ?></td>
                    <td><?= number_format($book['amount_paid'], 2) ?></td>
                    <td><?= number_format($book['balance'], 2) ?></td>
                    <td>
                        <input type="number" name="payments[<?= $book['id'] ?>]" min="0" max="<?= $book['balance'] ?>" step="0.01" required>
                    </td>
                </tr>
            <?php } ?>
        </table>

        <div class="form-group">
            <label for="payment_type">Payment Type:</label>
            <select name="payment_type" required>
                <option value="Cash">Cash</option>
                <option value="M-Pesa">M-Pesa</option>
                <option value="Bank Transfer">Bank Transfer</option>
            </select>
        </div>

        <div class="button-container">
            <button type="submit" class="add-student-btn">Clear Balance</button>
            <button type="button" class="add-student-btn"><a href="search-student.php">Back</a></button>
        </div>
    </form>

    <?php } ?>
</div>

<footer class="footer-dash">
    <p>&copy; <?= date("Y") ?> Kayron Junior School. All Rights Reserved.</p>
</footer>

</body>
</html>

<?php $conn->close(); ?>
