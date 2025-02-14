<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "school_database");

if ($conn->connect_error) {
    $_SESSION['error'] = "<div class='error-message'>Database connection failed.</div>";
    header("Location: process-uniform.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = trim($_POST['admission_no']);
    $receipt_no = trim($_POST['receipt_number']);
    $selected_uniforms = $_POST['uniforms'] ?? [];
    $sizes = $_POST['sizes'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $amounts_paid = $_POST['amounts_paid'] ?? [];
    $payment_type = $_POST['payment_type'];

    if (empty($selected_uniforms)) {
        $_SESSION['error'] = "<div class='error-message'>Please select at least one uniform.</div>";
        header("Location: process-uniform.php");
        exit();
    }

    // Validate student existence
    $stmt = $conn->prepare("SELECT name FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $student_result = $stmt->get_result();

    if ($student_result->num_rows === 0) {
        $_SESSION['error'] = "<div class='error-message'>Invalid admission number!</div>";
        header("Location: process-uniform.php");
        exit();
    }

    $student = $student_result->fetch_assoc();
    $student_name = $student['name'];

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Prepare uniform fetching and insertion statements
        $uniform_stmt = $conn->prepare("SELECT uniform_type, size, price FROM uniform_prices WHERE id = ?");
        $insert_stmt = $conn->prepare("INSERT INTO uniform_purchases (receipt_number, admission_no, name, uniform_type, size, quantity, total_price, amount_paid, balance, payment_type) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $_SESSION['receipt_uniforms'] = [];

        foreach ($selected_uniforms as $index => $uniform_id) {
            $quantity = (int)$quantities[$index];

            // Fetch uniform details
            $uniform_stmt->bind_param("i", $uniform_id);
            $uniform_stmt->execute();
            $uniform_result = $uniform_stmt->get_result();

            if ($uniform_result->num_rows > 0) {
                $uniform = $uniform_result->fetch_assoc();
                $uniform_type = $uniform['uniform_type'];
                $size = $uniform['size']; // Get size
                $price = $uniform['price'];
                $item_total = $price * $quantity;

                $amount_paid = isset($amounts_paid[$index]) ? floatval($amounts_paid[$index]) : 0;
                $balance = $item_total - $amount_paid;

                // Insert purchase record
                $insert_stmt->bind_param(
                    "sssssiddds",
                    $receipt_no,
                    $admission_no,
                    $student_name,
                    $uniform_type,
                    $size,
                    $quantity,
                    $item_total,
                    $amount_paid,
                    $balance,
                    $payment_type
                );
                $insert_stmt->execute();

                $_SESSION['receipt_uniforms'][] = [
                    'name' => $uniform_type,
                    'size' => $size,
                    'quantity' => $quantity,
                    'amount_paid' => $amount_paid
                ];
            }
        }

        $conn->commit();
        header("Location: receipt.php?receipt_number=$receipt_no&admission_no=$admission_no&payment_type=$payment_type");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "<div class='error-message'>Transaction failed: " . $e->getMessage() . "</div>";
        header("Location: process-uniform.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Uniform</title>
    <link rel="stylesheet" href="../style/style.css">
</head>
<body>

<div class="heading-all">
    <h2 class="title">Kayron Junior School</h2>
</div>

<div class="lunch-form">
    <div class="add-heading">
        <h2>Purchase Uniform</h2>
    </div>
    <form action="process-uniform.php" method="POST" id="purchase-form">
        <input type="hidden" id="receipt_number" name="receipt_number">

        <div class="form-group">
            <label for="admission_no">Admission Number:</label>
            <input type="text" id="admission_no" name="admission_no" placeholder="Enter Admission Number" required>
        </div>

        <h3>Select Uniforms:</h3>
        <div id="uniforms-list">
        <?php
        $stmt = $conn->prepare("SELECT id, uniform_type, size, price FROM uniform_prices");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($uniform = $result->fetch_assoc()):
        ?>
            <div class="uniform-item">
                <label>
                    <input type="checkbox" name="uniforms[]" value="<?= $uniform['id']; ?>" class="uniform-checkbox">
                    <?= $uniform['uniform_type'] . " - Size: " . $uniform['size'] . " (KES " . $uniform['price'] . ")"; ?>
                </label>
                <input type="hidden" name="sizes[]" value="<?= $uniform['size']; ?>">

                <input type="number" class="quantity" name="quantities[]" data-price="<?= $uniform['price']; ?>" min="1" value="1">
                <input type="number" class="amount-paid" name="amounts_paid[]" placeholder="Enter Amount Paid" min="0">
            </div>
        <?php endwhile; ?>
        </div>

        <p id="total_price">Total Price: KES 0.00</p>

        <div class="form-group">
            <label for="payment_type">Payment Type:</label>
            <select name="payment_type" required>
                <option value="Cash">Cash</option>
                <option value="M-Pesa">M-Pesa</option>
                <option value="Bank Transfer">Bank Transfer</option>
            </select>
        </div>

        <div class="button-container">
            <button type="submit" class="add-student-btn" id="purchase-btn">Purchase Uniform(s)</button>
            <button type="button" class="add-student-btn"><a href="./dashboard.php">Back to Dashboard</a></button>
        </div>
    </form>
</div>

<script>
    function generateReceiptNumber() {
        let timestamp = Date.now().toString(36).toUpperCase();
        let randomPart = Math.random().toString(36).substr(2, 5).toUpperCase();
        return "REC" + timestamp + randomPart;
    }

    function calculateTotal() {
        let total = 0;
        document.querySelectorAll(".uniform-item").forEach(item => {
            let checkbox = item.querySelector(".uniform-checkbox");
            let quantityInput = item.querySelector(".quantity");
            let price = parseFloat(quantityInput.dataset.price) || 0;
            let quantity = parseInt(quantityInput.value) || 1;

            if (checkbox.checked) {
                total += price * quantity;
            }
        });
        document.getElementById("total_price").textContent = `Total Price: KES ${total.toFixed(2)}`;
    }

    document.addEventListener("DOMContentLoaded", function () {
        document.getElementById("receipt_number").value = generateReceiptNumber();
        document.querySelectorAll(".uniform-checkbox, .quantity").forEach(element => {
            element.addEventListener("input", calculateTotal);
        });
    });
</script>

</body>
</html>
