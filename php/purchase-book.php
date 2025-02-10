<?php
session_start(); // Start session to store messages

// Database connection
$conn = new mysqli("localhost", "root", "", "school_database");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Generate a unique receipt number
$receipt_no = "RCPT-" . strtoupper(bin2hex(random_bytes(4)));

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = $_POST['admission_no'];
    $selected_books = $_POST['books']; // Array of selected book IDs
    $quantities = $_POST['quantities']; // Array of quantities

    // Fetch student details
    $student_query = "SELECT name, class FROM student_records WHERE admission_no = ?";
    $stmt = $conn->prepare($student_query);
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $student_result = $stmt->get_result();

    if ($student_result->num_rows > 0) {
        $student = $student_result->fetch_assoc();
        $name = $student['name'];
        $class = $student['class'];

        $total_price = 0;

        // Insert each book purchase
        foreach ($selected_books as $index => $book_id) {
            $quantity = (int)$quantities[$index];

            // Fetch book details (name & price) using book_id
            $book_query = "SELECT book_name, price FROM Books WHERE book_id = ?";
            $stmt = $conn->prepare($book_query);
            $stmt->bind_param("i", $book_id);
            $stmt->execute();
            $book_result = $stmt->get_result();

            if ($book_result->num_rows > 0) {
                $book = $book_result->fetch_assoc();
                $book_name = $book['book_name'];
                $price = $book['price'];
                $total = $price * $quantity;
                $total_price += $total;

                // Insert into Book_Purchases
                $insert_query = "INSERT INTO Book_Purchases (receipt_no, admission_no, name, class, book_id, book_name, quantity, total_price) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param("ssssisis", $receipt_no, $admission_no, $name, $class, $book_id, $book_name, $quantity, $total);
                $stmt->execute();
            }
        }

        $_SESSION['success'] = "<div class='success-message'>Purchase recorded successfully! Total: KES " . number_format($total_price, 2) . "</div>";
    } else {
        $_SESSION['error'] = "<div class='error-message'>Student not found!</div>";
    }

    // Redirect back to form
    header("Location: purchase-book.php");
    exit();
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
    <form action="" method="POST">

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

        <div class="form-group">
            <label for="admission_no">Admission Number:</label>
            <input type="text" id="admission_no" name="admission_no" placeholder="Enter Admission Number" required>
        </div>
        <div class="form-group">
            <label for="name">Student Name:</label>
            <input type="text" id="name" name="name" placeholder="Please enter name" required>
        </div>
        
        <h3>Select Books:</h3>
        <div id="books-list">
        <?php
        // Fetch books from database
        $stmt = $conn->prepare("SELECT book_id, category, book_name, price FROM Books");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($book = $result->fetch_assoc()):
        ?>
            <div class="book-item">
                <label class="book-label">
                    <input type="checkbox" name="books[]" value="<?= $book['book_id']; ?>">
                    <span class="book-info"><?= $book['book_name'] . "  - KES " . $book['price']; ?></span>
                </label>
                <input type="number" class="quantity" name="quantities[]" data-price="<?= $book['price']; ?>" min="1" value="1">
            </div>
        <?php endwhile; ?>
        </div>

        <div class="form-group">
            <p id="total_price">Total Price: KES 0.00</p>
        </div>

        <div class="form-group">
            <label for="amount_paid">Amount Paid (KES):</label>
            <input type="number" name="amount_paid" min="0" required>
        </div>
        
        <div class="button-container">
            <button type="submit" class="add-student-btn">Purchase</button>
            <button type="button" class="add-student-btn"><a href="./dashboard.php">Back to Dashboard</a></button>
        </div>

    </form>
</div>

<footer class="footer-dash">
    <p>&copy; <?php echo date("Y")?> Kayron Junior School. All Rights Reserved.</p>
</footer>

<script>
document.addEventListener("DOMContentLoaded", function () {
    function updateTotalPrice() {
        let total = 0;

        document.querySelectorAll('.book-item input[type="checkbox"]:checked').forEach(function (checkbox) {
            let quantityInput = checkbox.closest('.book-item').querySelector('.quantity');
            let price = parseFloat(quantityInput.getAttribute('data-price')) || 0;
            let quantity = parseInt(quantityInput.value) || 1;

            total += price * quantity;
        });

        document.getElementById("total_price").textContent = "Total Price: KES " + total.toFixed(2);
    }

    document.querySelectorAll('.quantity').forEach(function (input) {
        input.addEventListener("input", updateTotalPrice);
    });

    updateTotalPrice();
});
</script>
</body>
</html>

<?php $conn->close(); ?>
