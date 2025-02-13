<?php
session_start(); // Start session to store messages

// Database connection
$conn = new mysqli("localhost", "root", "", "school_database");

if ($conn->connect_error) {
    $_SESSION['error'] = "<div class='error-message'>Database connection failed.</div>";
    header("Location: purchase-book.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = trim($_POST['admission_no']);
    $receipt_no = trim($_POST['receipt_number']); // Get receipt number from form
    $selected_books = $_POST['books'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $amounts_paid = $_POST['amounts_paid'] ?? [];
    $payment_type = $_POST['payment_type'];

    if (empty($selected_books)) {
        $_SESSION['error'] = "<div class='error-message'>Please select at least one book.</div>";
        header("Location: purchase-book.php");
        exit();
    }

    // Validate student existence
    $stmt = $conn->prepare("SELECT name FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $student_result = $stmt->get_result();

    if ($student_result->num_rows === 0) {
        $_SESSION['error'] = "<div class='error-message'>Invalid admission number!</div>";
        header("Location: purchase-book.php");
        exit();
    }

    $student = $student_result->fetch_assoc();
    $student_name = $student['name'];

    // Begin database transaction
    $conn->begin_transaction();
    $total_price = 0;

    try {
        // Prepare book fetching and insertion statements
        $book_stmt = $conn->prepare("SELECT book_name, price FROM book_prices WHERE book_id = ?");
        $insert_stmt = $conn->prepare("INSERT INTO book_purchases (receipt_number, admission_no, name, book_name, quantity, total_price, amount_paid, balance, payment_type) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($selected_books as $index => $book_id) {
            $quantity = (int)$quantities[$index];

            // Fetch book details
            $book_stmt->bind_param("i", $book_id);
            $book_stmt->execute();
            $book_result = $book_stmt->get_result();

            if ($book_result->num_rows > 0) {
                $book = $book_result->fetch_assoc();
                $book_name = $book['book_name'];
                $price = $book['price'];
                $item_total = $price * $quantity;
                $total_price += $item_total;

                // Get amount paid for this book
                $amount_paid = isset($amounts_paid[$index]) ? floatval($amounts_paid[$index]) : 0;
                $balance = $item_total - $amount_paid;

                // Insert purchase record
                $insert_stmt->bind_param(
                    "ssssiddds",
                    $receipt_no,
                    $admission_no,
                    $student_name,
                    $book_name,
                    $quantity,
                    $item_total,
                    $amount_paid,
                    $balance,
                    $payment_type
                );
                $insert_stmt->execute();
            }
        }

        // Commit transaction
        $conn->commit();

        // Redirect to receipt page with necessary details
        header("Location: receipt.php?receipt_number=$receipt_no&admission_no=$admission_no&payment_type=$payment_type&total=$total_price");
        exit();
        
    } catch (Exception $e) {
        // Rollback in case of error
        $conn->rollback();
        $_SESSION['error'] = "<div class='error-message'>Transaction failed: " . $e->getMessage() . "</div>";
        header("Location: purchase-book.php");
        exit();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Book</title>
    <link rel="stylesheet" href="../style/style.css">
</head>
<body>

<div class="heading-all">
    <h2 class="title">Kayron Junior School</h2>
</div>

<div class="lunch-form">
    <div class="add-heading">
        <h2>Purchase Books</h2>
    </div>
    <form action="" method="POST" id="purchase-form">

        <?php
        if (isset($_SESSION['success'])) {
            echo "<p class='message success'>" . $_SESSION['success'] . "</p>";
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            echo "<p class='message error'>" . $_SESSION['error'] . "</p>";
            unset($_SESSION['error']);
        }
        ?>

        <input type="hidden" id="receipt_number" name="receipt_number">

        <div class="form-group">
            <label for="admission_no">Admission Number:</label>
            <input type="text" id="admission_no" name="admission_no" placeholder="Enter Admission Number" required>
        </div>

        <h3>Select Books:</h3>
        <div id="books-list">
        <?php
        $stmt = $conn->prepare("SELECT book_id, category, book_name, price FROM book_prices");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($book = $result->fetch_assoc()):
        ?>
            <div class="book-item">
                <label class="book-label">
                    <input type="checkbox" name="books[]" value="<?= $book['book_id']; ?>" class="book-checkbox">
                    <span class="book-info"><?= $book['book_name'] . " (KES " . $book['price'] . ")"; ?></span>
                </label>
                <input type="number" class="quantity" name="quantities[]" data-price="<?= $book['price']; ?>" min="1" value="1" >
                <input type="number" class="amount-paid" name="amounts_paid[]" placeholder="Enter Amount Paid (KES)" min="0">
            </div>
        <?php endwhile; ?>
        </div>

        <div class="">
            <p id="total_price">Total Price: KES 0.00</p>
        </div>

        <div class="form-group">
            <label for="payment_type">Payment Type:</label>
            <select name="payment_type" required>
                <option value="Cash">Cash</option>
                <option value="M-Pesa">M-Pesa</option>
                <option value="Bank Transfer">Bank Transfer</option>
            </select>
        </div>

        <div class="button-container">
            <button type="submit" class="add-student-btn" id="purchase-btn">Purchase</button>
            <button type="button" class="add-student-btn"><a href="./dashboard.php">Back to Dashboard</a></button>
        </div>

    </form>
</div>

<footer class="footer-dash">
    <p>&copy; <?= date("Y") ?> Kayron Junior School. All Rights Reserved.</p>
</footer>

<script>
    function generateReceiptNumber() {
        let timestamp = Date.now().toString(36).toUpperCase();
        let randomPart = Math.random().toString(36).substr(2, 5).toUpperCase();
        return "REC" + timestamp + randomPart;
    }

    function calculateTotal() {
        let total = 0;
        document.querySelectorAll(".book-item").forEach(item => {
            let checkbox = item.querySelector(".book-checkbox");
            let quantityInput = item.querySelector(".quantity");
            let price = parseFloat(quantityInput.dataset.price) || 0;
            let quantity = parseInt(quantityInput.value) || 1;

            if (checkbox.checked) {
                total += price * quantity;
            }
        });

        document.getElementById("total_price").textContent = `Total Price: KES ${total.toFixed(2)}`;
        return total.toFixed(2);
    }


    document.addEventListener("DOMContentLoaded", function () {
        document.getElementById("receipt_number").value = generateReceiptNumber();

        // Event listeners for dynamic total price calculation
        document.querySelectorAll(".book-checkbox, .quantity").forEach(element => {
            element.addEventListener("input", calculateTotal);
        });

        // Handle button status and allow form submission
        document.getElementById("purchase-form").addEventListener("submit", function (e) {
            let purchaseBtn = document.getElementById("purchase-btn");
            purchaseBtn.textContent = "Processing...";
            purchaseBtn.disabled = true;

            // Wait for a few seconds to show processing effect
            setTimeout(() => {
                purchaseBtn.textContent = "Paid";
                purchaseBtn.style.backgroundColor = "green";

                // **Do NOT prevent form submission**
                this.submit(); // Allows form to submit normally after processing
            }, 3000);
        });
    });
</script>


</body>
</html>

<?php $conn->close(); ?>
