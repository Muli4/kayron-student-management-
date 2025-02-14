<?php
session_start();
$conn = new mysqli("localhost", "root", "", "school_database");

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = trim($_POST['admission_no']);
    $receipt_no = trim($_POST['receipt_number']);
    $selected_uniforms = $_POST['uniforms'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $sizes = $_POST['sizes'] ?? [];
    $amounts_paid = $_POST['amounts_paid'] ?? [];
    $payment_type = $_POST['payment_type'];

    if (empty($selected_uniforms)) {
        echo json_encode(["status" => "error", "message" => "Please select at least one uniform."]);
        exit();
    }

    // Validate student existence
    $stmt = $conn->prepare("SELECT name FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $student_result = $stmt->get_result();

    if ($student_result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Invalid admission number!"]);
        exit();
    }

    $student = $student_result->fetch_assoc();
    $student_name = $student['name'];

    $conn->begin_transaction();
    try {
        $uniform_stmt = $conn->prepare("SELECT uniform_type, size, price FROM uniform_prices WHERE id = ?");
        $insert_stmt = $conn->prepare("INSERT INTO uniform_purchases (receipt_number, admission_no, name, uniform_type, size, quantity, total_price, amount_paid, balance, payment_type) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($selected_uniforms as $index => $uniform_id) {
            $quantity = (int)$quantities[$index];
            $size = $sizes[$index];

            // Fetch uniform details
            $uniform_stmt->bind_param("i", $uniform_id);
            $uniform_stmt->execute();
            $uniform_result = $uniform_stmt->get_result();

            if ($uniform_result->num_rows > 0) {
                $uniform = $uniform_result->fetch_assoc();
                $uniform_type = $uniform['uniform_type'];
                $price = $uniform['price'];
                $item_total = $price * $quantity;

                $amount_paid = isset($amounts_paid[$index]) ? floatval($amounts_paid[$index]) : 0;
                $balance = $item_total - $amount_paid;

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
            }
        }

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Payment successful. Redirecting to print....", "redirect" => "receipt.php?receipt_number=$receipt_no&admission_no=$admission_no&payment_type=$payment_type"]);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "Transaction failed: " . $e->getMessage()]);
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="heading-all">
    <h2 class="title">Kayron Junior School</h2>
</div>

<div class="lunch-form">
    <div class="add-heading">
        <h2>Purchase Uniform</h2>
        <p id="message" class="status-message"></p> <!-- ✅ Message appears here -->
    </div>

    <form id="purchase-form">
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
            <button type="submit" id="purchase-btn" class="add-student-btn">Purchase Uniform(s)</button>
            <button type="button" class="add-student-btn"><a href="./dashboard.php">Back to Dashboard</a></button>
        </div>
    </form>
</div>

<style>
    .status-message {
        display: none;
        padding: 10px;
        margin: 10px 0;
        border-radius: 5px;
        font-weight: bold;
        text-align: center;
    }

    .success {
        background-color: #4CAF50; /* Green */
        color: white;
    }

    .error {
        background-color: #F44336; /* Red */
        color: white;
    }
</style>

<script>
    function generateReceiptNumber() {
        return "REC" + Date.now().toString(36).toUpperCase() + Math.random().toString(36).substr(2, 5).toUpperCase();
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

    $(document).ready(function () {
        $("#receipt_number").val(generateReceiptNumber());

        $(".uniform-checkbox, .quantity").on("input", calculateTotal);

        $("#purchase-form").submit(function (e) {
            e.preventDefault();

            let button = $("#purchase-btn");
            let message = $("#message");
            message.removeClass("success error").hide();
            button.text("Processing...").css("background-color", "orange");

            $.post("process-uniform.php", $(this).serialize(), function (response) {
                message.text(response.message).fadeIn().addClass(response.status === "success" ? "success" : "error");

                if (response.status === "success") {
                    button.text("Paid").css("background-color", "green");
                    setTimeout(() => window.location.href = response.redirect, 2000);
                } else {
                    button.text("Retry").css("background-color", "red");
                }

                setTimeout(() => message.fadeOut(), 5000);
            }, "json");
        });
    });
</script>

</body>
</html>
