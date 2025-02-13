<?php
session_start();
include 'db.php'; // Database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];

    if ($action === "purchase") {
        $admission_no = trim($_POST['admission_no']);
        $payment_type = $_POST['payment_type'] ?? 'Cash';
        $uniforms = $_POST['uniforms']; // Array of selected uniforms

        // Verify admission number exists in student records
        $stmt = $conn->prepare("SELECT name FROM student_records WHERE admission_no = ?");
        $stmt->bind_param("s", $admission_no);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $_SESSION['message'] = "<div class='error-message'>Error: Admission number not found in student records!</div>";
            header("Location: process-uniform.php");
            exit();
        }

        $student = $result->fetch_assoc();
        $name = $student['name'];
        $stmt->close();

        // Fetch uniform prices
        $price_map = [];
        if (!empty($uniforms)) {
            $placeholders = implode(',', array_fill(0, count($uniforms), '?'));
            $stmt = $conn->prepare("SELECT id, uniform_type, price FROM uniform_prices WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($uniforms)), ...array_keys($uniforms));
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $price_map[$row['id']] = ['price' => $row['price'], 'type' => $row['uniform_type']];
            }
            $stmt->close();
        }

        // Process uniform purchases
        $items = [];
        foreach ($uniforms as $uniform_id => $data) {
            $quantity = isset($data['quantity']) ? intval($data['quantity']) : 0;
            $amount_paid = isset($data['amount_paid']) ? floatval($data['amount_paid']) : 0;

            if ($quantity > 0 && isset($price_map[$uniform_id])) {
                $price = $price_map[$uniform_id]['price'];
                $uniform_type = $price_map[$uniform_id]['type'];
                $subtotal = $price * $quantity;
                $balance = $subtotal - $amount_paid; // Balance stored but not displayed

                $items[] = [
                    'uniform_type' => $uniform_type,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                    'amount_paid' => $amount_paid,
                    'balance' => $balance
                ];
            }
        }

        if (empty($items)) {
            $_SESSION['message'] = "<div class='error-message'>Error: No uniforms selected!</div>";
            header("Location: process-uniform.php");
            exit();
        }

        $receipt_number = "U-" . strtoupper(uniqid()) . "-" . mt_rand(1000, 9999);

        // **Begin Transaction**
        $conn->begin_transaction();

        try {
            // Insert each uniform purchase
            $stmt = $conn->prepare("INSERT INTO uniform_purchases (receipt_number, name, admission_no, uniform_type, quantity, total_price, amount_paid, balance, payment_type) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($items as $item) {
                $stmt->bind_param("sssiiidds", 
                    $receipt_number, 
                    $name, 
                    $admission_no, 
                    $item['uniform_type'], 
                    $item['quantity'], 
                    $item['subtotal'],  
                    $item['amount_paid'], 
                    $item['balance'], 
                    $payment_type
                );
                if (!$stmt->execute()) {
                    throw new Exception("Error inserting into uniform_purchases.");
                }
            }

            // **Commit transaction if successful**
            $conn->commit();
            $_SESSION['message'] = "<div class='success-message'>Uniform purchase recorded successfully!</div>";

        } catch (Exception $e) {
            // **Rollback if there is an error**
            $conn->rollback();
            $_SESSION['message'] = "<div class='error-message'>" . $e->getMessage() . "</div>";
        }

        $stmt->close();
        $conn->close();
    }

    header("Location: process-uniform.php");
    exit();
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uniform Purchase</title>
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
        <?php
        if (isset($_SESSION['message'])) {
            echo $_SESSION['message'];
            unset($_SESSION['message']);
        }
        ?>
        <form action="process-uniform.php" method="POST">
            <input type="hidden" name="action" value="purchase">

            <div class="form-group">
                <label for="admission_no">Admission Number:</label>
                <input type="text" id="admission_no" name="admission_no" required oninput="fetchStudentName()">
            </div>

            <div class="form-group">
                <label for="name">Student Name:</label>
                <input type="text" id="name" name="name" required>
            </div>

            <h3>Select Uniforms:</h3>
            <div id="uniforms-list">
            <?php
            include 'db.php'; // Include DB connection
            $stmt = $conn->prepare("SELECT id, uniform_type, price FROM uniform_prices");
            $stmt->execute();
            $result = $stmt->get_result();
            while ($uniform = $result->fetch_assoc()):
            ?>
            <div class="uniform-item">
                <label class="uniform-label">
                    <input type="checkbox" class="uniform-checkbox" data-id="<?= $uniform['id']; ?>" data-price="<?= $uniform['price']; ?>">
                    <span class="uniform-info"><?= $uniform['uniform_type'] . " (KES " . $uniform['price'] . ")"; ?></span>
                </label>
                <input type="number" class="quantity" name="uniforms[<?= $uniform['id']; ?>][quantity]" data-price="<?= $uniform['price']; ?>" min="1" value="0" disabled onchange="updateTotalPrice()">
                <input type="number" class="amount-paid" name="uniforms[<?= $uniform['id']; ?>][amount_paid]" min="0" value="0" disabled>
            </div>
            <?php endwhile; ?>
            </div>

            <div class="form-group">
                <p id="total_price">Total Price: KES 0.00</p>
            </div>

            <div class="form-group">
                <label for="payment_type">Payment Type:</label>
                <select name="payment_type" required>
                    <option value="Cash">Cash</option>
                    <option value="m-pesa">M-Pesa</option>
                    <option value="bank-transfer">Bank Transfer</option>
                </select>
            </div>

            <div class="button-container">
                <button type="submit" class="add-student-btn">Purchase</button>
                <button type="button" class="add-student-btn">
                    <a href="./dashboard.php">Back to Dashboard</a>
                </button>
            </div>
        </form>
    </div>

    <footer class="footer-dash">
        <p>&copy; <?php echo date("Y")?> Kayron Junior School. All Rights Reserved.</p>
    </footer>

    <script>
        function updateTotalPrice() {
            var total = 0;
            document.querySelectorAll(".uniform-item").forEach(function(row) {
                var checkbox = row.querySelector(".uniform-checkbox");
                var quantityInput = row.querySelector(".quantity");
                var price = parseFloat(quantityInput.dataset.price) || 0;
                var quantity = parseInt(quantityInput.value) || 0;

                if (checkbox.checked && quantity > 0) {
                    total += price * quantity;
                }
            });

            document.getElementById("total_price").textContent = "Total Price: KES " + total.toFixed(2);
        }

        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".uniform-checkbox").forEach(function(checkbox) {
                checkbox.addEventListener("change", function () {
                    var row = this.closest(".uniform-item");
                    var quantityInput = row.querySelector(".quantity");
                    var amountPaidInput = row.querySelector(".amount-paid");

                    if (this.checked) {
                        quantityInput.value = 1; 
                        quantityInput.disabled = false;
                        amountPaidInput.disabled = false;
                    } else {
                        quantityInput.value = 0;
                        amountPaidInput.value = 0;
                        quantityInput.disabled = true;
                        amountPaidInput.disabled = true;
                    }
                    updateTotalPrice();
                });
            });

            document.querySelectorAll(".quantity").forEach(function(input) {
                input.addEventListener("input", updateTotalPrice);
            });
        });
    </script>
</body>
</html>

