<?php
session_start();
include 'db.php'; // Database connection

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];

    if ($action === "purchase") {
        $admission_no = trim($_POST['admission_no']);
        $uniforms = $_POST['uniforms']; // Array of selected uniforms

        // Validate admission number
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
        $placeholders = implode(',', array_fill(0, count($uniforms), '?'));
        $query = "SELECT id, uniform_type, price FROM uniform_prices WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($query);

        $types = str_repeat('i', count($uniforms)); 
        $stmt->bind_param($types, ...array_keys($uniforms)); 
        $stmt->execute();
        $result = $stmt->get_result();

        $price_map = [];
        while ($row = $result->fetch_assoc()) {
            $price_map[$row['id']] = ['price' => $row['price'], 'type' => $row['uniform_type']];
        }
        $stmt->close();

        // Process each uniform purchase
        $total_price = 0;
        $items = [];
        foreach ($uniforms as $uniform_id => $data) {
            $quantity = intval($data['quantity']) ?? 0;
            $amount_paid = floatval($data['amount_paid']) ?? 0;

            if ($quantity > 0 && isset($price_map[$uniform_id])) { 
                $price = $price_map[$uniform_id]['price'];
                $uniform_type = $price_map[$uniform_id]['type'];
                $subtotal = $price * $quantity;
                $total_price += $subtotal;
                $balance = $subtotal - $amount_paid;

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

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("INSERT INTO uniform_purchases (receipt_number, name, admission_no, uniform_type, quantity, total_price, amount_paid, balance) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($items as $item) {
                $stmt->bind_param(
                    "sssisiid",
                    $receipt_number,
                    $name,
                    $admission_no,
                    $item['uniform_type'],
                    $item['quantity'],
                    $item['subtotal'],
                    $item['amount_paid'],
                    $item['balance']
                );

                if (!$stmt->execute()) {
                    throw new Exception("Error inserting into uniform_purchases.");
                }
            }

            $conn->commit();
            $_SESSION['message'] = "<div class='success-message'>Uniform purchase recorded successfully!</div>";

        } catch (Exception $e) {
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

    <h2>Purchase Uniform</h2>

    <form action="process-uniform.php" method="POST">
        <input type="hidden" name="action" value="purchase">

        <label for="admission_no">Admission No:</label>
        <input type="text" id="admission_no" name="admission_no" required oninput="fetchStudentName()">
        
        <label for="name">Student Name:</label>
        <input type="text" id="name" name="name" required >

        <h3>Select Uniforms:</h3>
        <div id="uniforms-list">
        <?php
        include 'db.php';
        $stmt = $conn->prepare("SELECT id, uniform_type, size, price FROM uniform_prices");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($uniform = $result->fetch_assoc()):
        ?>
        <div class="uniform-item">
            <label>
               <input type="checkbox" class="uniform-checkbox" data-id="<?= $uniform['id']; ?>" data-price="<?= $uniform['price']; ?>">
                <?= $uniform['uniform_type'] . " - " . $uniform['size'] . " (KES " . $uniform['price'] . ")"; ?>
            </label>
            <input type="number" class="quantity" name="uniforms[<?= $uniform['id']; ?>][quantity]" min="1" value="1" disabled onchange="updateTotalPrice()">
            <input type="number" class="amount-paid" name="uniforms[<?= $uniform['id']; ?>][amount_paid]" min="0" value="0" disabled>
        </div>
        <?php endwhile; ?>
        </div>

        <p id="total_price">Total Price: KES 0.00</p>

        <label for="payment_type">Payment Type:</label>
        <select name="payment_type" required>
            <option value="Cash">Cash</option>
            <option value="m-pesa">M-Pesa</option>
            <option value="bank-transfer">Bank Transfer</option>
        </select>

        <button type="submit">Purchase</button>
    </form>

    <script>
        function updateTotalPrice() {
            let total = 0;
            document.querySelectorAll(".uniform-item").forEach(row => {
                let checkbox = row.querySelector(".uniform-checkbox");
                let quantityInput = row.querySelector(".quantity");
                let price = parseFloat(quantityInput.dataset.price) || 0;
                let quantity = parseInt(quantityInput.value) || 0;

                if (checkbox.checked && quantity > 0) {
                    total += price * quantity;
                }
            });
            document.getElementById("total_price").textContent = "Total Price: KES " + total.toFixed(2);
        }

        document.querySelectorAll(".uniform-checkbox").forEach(checkbox => {
            checkbox.addEventListener("change", function () {
                let row = this.closest(".uniform-item");
                let quantityInput = row.querySelector(".quantity");
                let amountPaidInput = row.querySelector(".amount-paid");

                quantityInput.disabled = !this.checked;
                amountPaidInput.disabled = !this.checked;
                updateTotalPrice();
            });
        });
    </script>

</body>
</html>
