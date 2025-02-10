<?php
session_start();
include 'db.php'; // Database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];

    if ($action === "purchase") {
        $admission_no = trim($_POST['admission_no']);
        $amount_paid = isset($_POST['amount_paid']) ? floatval($_POST['amount_paid']) : 0.0;
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

        // Calculate total price
        $total_price = 0;
        $items = [];

        // Fetch uniform prices
        $uniform_ids = implode(',', array_keys($uniforms));
        $stmt = $conn->prepare("SELECT id, price FROM uniform_prices WHERE id IN ($uniform_ids)");
        $stmt->execute();
        $result = $stmt->get_result();
        $price_map = [];

        while ($row = $result->fetch_assoc()) {
            $price_map[$row['id']] = $row['price'];
        }
        $stmt->close();

        foreach ($uniforms as $uniform_id => $quantity) {
            if ($quantity > 0 && isset($price_map[$uniform_id])) {
                $price = $price_map[$uniform_id];
                $subtotal = $price * $quantity;
                $total_price += $subtotal;
                $items[] = ['uniform_id' => $uniform_id, 'quantity' => $quantity, 'subtotal' => $subtotal];
            }
        }

        if (empty($items)) {
            $_SESSION['message'] = "<div class='error-message'>Error: No uniforms selected!</div>";
            header("Location: process-uniform.php");
            exit();
        }

        $balance = $total_price - $amount_paid;
        $receipt_number = "U-" . strtoupper(uniqid()) . "-" . mt_rand(1000, 9999);

        // **Begin Transaction**
        $conn->begin_transaction();

        try {
            // Insert into uniform_purchases
            $stmt = $conn->prepare("INSERT INTO uniform_purchases (receipt_number, name, admission_no, total_price, amount_paid, balance, payment_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssddds", $receipt_number, $name, $admission_no, $total_price, $amount_paid, $balance, $payment_type);
            if (!$stmt->execute()) {
                throw new Exception("Error inserting into uniform_purchases.");
            }
            $purchase_id = $stmt->insert_id;

            // Insert into uniform_purchase_items
            foreach ($items as $item) {
                $stmt = $conn->prepare("INSERT INTO uniform_purchase_items (purchase_id, uniform_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiid", $purchase_id, $item['uniform_id'], $item['quantity'], $item['subtotal']);
                if (!$stmt->execute()) {
                    throw new Exception("Error inserting into uniform_purchase_items.");
                }
            }

            // **Commit transaction if everything is successful**
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
                <input type="text" id="name" name="name" required >
            </div>
            <h3>Select Uniforms:</h3>
            <div id="uniforms-list">
            <?php
            // Fetch uniforms from database
            $stmt = $conn->prepare("SELECT id, uniform_type, size, price FROM uniform_prices");
            $stmt->execute();
            $result = $stmt->get_result();
            while ($uniform = $result->fetch_assoc()):
            ?>
            <div class="uniform-item">
                <label class="uniform-label">
                    <input type="checkbox" name="uniforms[<?= $uniform['id']; ?>]" value="1">
                    <span class="uniform-info"><?= $uniform['uniform_type'] . " - " . $uniform['size'] . " (KES " . $uniform['price'] . ")"; ?></span>
                </label>
                <input type="number" class="quantity" name="uniforms[<?= $uniform['id']; ?>]" data-price="<?= $uniform['price']; ?>" min="1" value="1" onchange="updateTotalPrice()">
            </div>
            <?php endwhile; ?>
            </div>

            <div class="">
                <p id="total_price">Total Price: KES 0.00</p>
            </div>

            <div class="form-group">
                <label for="amount_paid">Amount Paid (KES):</label>
                <input type="number" name="amount_paid" min="0" required>
            </div>

            <div class="button-container">
                <button type="submit" class="add-student-btn">Purchase</button>
                <button type="button" class="add-student-btn"><a href="./dashboard.php">Back to dashboard</a></button>
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
                var checkbox = row.querySelector("input[type='checkbox']");
                var quantityInput = row.querySelector(".quantity");
                var price = parseFloat(quantityInput.dataset.price) || 0;
                var quantity = parseInt(quantityInput.value) || 0;

                // Only add price if the checkbox is checked and quantity is greater than 0
                if (checkbox.checked && quantity > 0) {
                    total += price * quantity;
                }
            });
            document.getElementById("total_price").textContent = "Total Price: KES " + total.toFixed(2);
        }
        
        // Attach event listeners to checkboxes and quantity inputs
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".uniform-item").forEach(function(row) {
                var checkbox = row.querySelector("input[type='checkbox']");
                var quantityInput = row.querySelector(".quantity");

                // Update total price when checkbox is clicked
                checkbox.addEventListener("change", function() {
                    if (!this.checked) {
                        quantityInput.value = 0; // Reset quantity if unchecked
                    }
                    updateTotalPrice();
                });
                // Update total price when quantity is changed
                quantityInput.addEventListener("input", updateTotalPrice);
            });
        });    
    </script>
</body>
</html>
