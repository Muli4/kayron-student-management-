<?php
session_start();
include 'db.php'; // Database connection

// Handle POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];

    if ($action === "purchase") {
        $name = trim($_POST['name']);
        $admission_no = trim($_POST['admission_no']);
        $uniforms = $_POST['uniforms']; // Array of selected uniforms
        $amount_paid = isset($_POST['amount_paid']) ? floatval($_POST['amount_paid']) : 0.0;
        $payment_type = $_POST['payment_type'] ?? 'Cash';

        $total_price = 0;
        $items = [];

        // Fetch all uniform prices in a single query to avoid multiple database calls
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
            header("Location: uniform_purchase.php");
            exit();
        }

        $balance = $total_price - $amount_paid;
        $receipt_number = "U-" . strtoupper(uniqid()) . "-" . mt_rand(1000, 9999);

        // Insert the main purchase record
        $stmt = $conn->prepare("INSERT INTO uniform_purchases (receipt_number, name, admission_no, total_price, amount_paid, balance) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssddd", $receipt_number, $name, $admission_no, $total_price, $amount_paid, $balance);
        if ($stmt->execute()) {
            $purchase_id = $stmt->insert_id;

            // Insert each selected uniform into uniform_purchase_items
            foreach ($items as $item) {
                $stmt = $conn->prepare("INSERT INTO uniform_purchase_items (purchase_id, uniform_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiid", $purchase_id, $item['uniform_id'], $item['quantity'], $item['subtotal']);
                $stmt->execute();
            }

            // Insert payment record if any amount was paid
            if ($amount_paid > 0) {
                $stmt = $conn->prepare("INSERT INTO uniform_purchases (receipt_number, purchase_id, amount_paid, payment_type) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sids", $receipt_number, $purchase_id, $amount_paid, $payment_type);
                $stmt->execute();
            }

            $_SESSION['message'] = "<div class='success-message'>Uniform purchase recorded successfully!</div>";
        } else {
            $_SESSION['message'] = "<div class='error-message'>Error recording purchase!</div>";
        }
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
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 20px;
        }
        .container {
            width: 50%;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
        }
        label {
            font-weight: bold;
        }
        select, input {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            border: none;
            color: white;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #218838;
        }
        .error-message {
            color: red;
            text-align: center;
        }
        .success-message {
            color: green;
            text-align: center;
        }
    </style>
    <script>
        function updateTotalPrice() {
            var total = 0;
            document.querySelectorAll(".uniform-item").forEach(function(row) {
                var quantity = row.querySelector(".quantity").value;
                var price = row.querySelector(".quantity").dataset.price;
                if (quantity > 0) {
                    total += price * quantity;
                }
            });
            document.getElementById("total_price").textContent = "Total Price: KES " + total.toFixed(2);
        }

        function fetchStudentName() {
            var admissionNo = document.getElementById("admission_no").value;
            if (admissionNo.length > 0) {
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "fetch_student.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        document.getElementById("name").value = xhr.responseText;
                    }
                };
                xhr.send("admission_no=" + admissionNo);
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <h2>Uniform Purchase</h2>
        <?php
        if (isset($_SESSION['message'])) {
            echo $_SESSION['message'];
            unset($_SESSION['message']);
        }
        ?>
        <form action="process-uniform.php" method="POST">
            <input type="hidden" name="action" value="purchase">

            <label for="admission_no">Admission Number:</label>
            <input type="text" id="admission_no" name="admission_no" required oninput="fetchStudentName()">

            <label for="name">Student Name:</label>
            <input type="text" id="name" name="name" required>

            <h3>Select Uniforms:</h3>
            <div id="uniforms-list">
                <?php 
                // Fetch uniforms from the database
                $stmt = $conn->prepare("SELECT id, uniform_type, size, price FROM uniform_prices");
                $stmt->execute();
                $result = $stmt->get_result();
                while ($uniform = $result->fetch_assoc()): 
                ?>
                    <div class="uniform-item">
                        <label>
                            <input type="checkbox" name="uniforms[<?= $uniform['id']; ?>]" value="1">
                            <?= $uniform['uniform_type'] . " - " . $uniform['size'] . " (KES " . $uniform['price'] . ")"; ?>
                        </label>
                        <input type="number" class="quantity" name="uniforms[<?= $uniform['id']; ?>]" data-price="<?= $uniform['price']; ?>" min="0" value="0" onchange="updateTotalPrice()">
                    </div>
                <?php endwhile; ?>
            </div>

            <p id="total_price" style="text-align:center; font-weight:bold;">Total Price: KES 0.00</p>

            <label for="amount_paid">Amount Paid (KES):</label>
            <input type="number" name="amount_paid" min="0" required>

            <button type="submit">Submit Purchase</button>
        </form>
    </div>
</body>
</html>
