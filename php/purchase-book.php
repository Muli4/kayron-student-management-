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
    $book_id = $_POST['book_id'];

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

        // Fetch book details (name & price)
        $book_query = "SELECT book_name, price FROM Books WHERE book_id = ?";
        $stmt = $conn->prepare($book_query);
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $book_result = $stmt->get_result();

        if ($book_result->num_rows > 0) {
            $book = $book_result->fetch_assoc();
            $book_name = $book['book_name'];
            $price = $book['price'];

            // Insert into Book_Purchases (Removed `term`)
            $insert_query = "INSERT INTO Book_Purchases (receipt_no, admission_no, name, class, book_id, book_name, quantity, total_price) 
                             VALUES (?, ?, ?, ?, ?, ?, 1, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ssssisd", $receipt_no, $admission_no, $name, $class, $book_id, $book_name, $price);

            if ($stmt->execute()) {
                $_SESSION['success'] = "<div class='success-message'>Purchase recorded successfully!</div>";
            } else {
                $_SESSION['error'] = "Error: " . $stmt->error;
            }
        } else {
            $_SESSION['error'] = "<div class='error-message'>Book not found!</div>";
        }
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
    <div class="add-heading">
        <h2>Purchase a Book</h2>
    </div>

    <div class="lunch-form">

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
        <label for="book_id">Select Book:</label>
        <select id="book_id" name="book_id" required>
            <option value="">-- Select a Book --</option>
            <?php
                // Fetch books from database
                $book_sql = "SELECT book_id, book_name FROM Books";
                $book_result = $conn->query($book_sql);

                while ($row = $book_result->fetch_assoc()) {
                    echo "<option value='{$row['book_id']}'>{$row['book_name']}</option>";
                }
            ?>
        </select>
        </div>

        <div class="button-container">
            <button type="submit" class="add-student-btn">Purchase</button>
            <button type="button" class="add-student-btn"><a href="./dashboard.php">Back to dashboard</a></button>
        </div>

    </form>
    </div>

    <footer class="footer">
        <p>&copy; <?php echo date("Y")?> Kayron Junior School. All Rights Reserved.</p>
    </footer>
</body>
</html>

<?php $conn->close(); ?>
