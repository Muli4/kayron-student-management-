<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "school_database");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Generate a unique receipt number
$receipt_no = "RCPT-" . strtoupper(uniqid());

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = $_POST['admission_no'];
    $book_id = $_POST['book_id'];

    // Fetch student details
    $student_query = "SELECT name, class, term FROM student_records WHERE admission_no = ?";
    $stmt = $conn->prepare($student_query);
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $student_result = $stmt->get_result();

    if ($student_result->num_rows > 0) {
        $student = $student_result->fetch_assoc();
        $name = $student['name'];
        $class = $student['class'];
        $term = $student['term'];

        // Fetch book details (price and name)
        $book_query = "SELECT book_name, price FROM Books WHERE book_id = ?";
        $stmt = $conn->prepare($book_query);
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $book_result = $stmt->get_result();

        if ($book_result->num_rows > 0) {
            $book = $book_result->fetch_assoc();
            $book_name = $book['book_name'];
            $price = $book['price'];

            // Insert into Book_Purchases
            $insert_query = "INSERT INTO Book_Purchases (receipt_no, admission_no, name, class, term, book_id, book_name, quantity, total_price) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ssssissd", $receipt_no, $admission_no, $name, $class, $term, $book_id, $book_name, $price);

            if ($stmt->execute()) {
                echo "<p style='color: green;'>Purchase recorded successfully! Receipt No: <strong>$receipt_no</strong></p>";
            } else {
                echo "<p style='color: red;'>Error: " . $stmt->error . "</p>";
            }
        } else {
            echo "<p style='color: red;'>Book not found!</p>";
        }
    } else {
        echo "<p style='color: red;'>Student not found!</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Book</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        form { max-width: 400px; padding: 20px; border: 1px solid #ccc; border-radius: 10px; }
        label { display: block; margin-top: 10px; }
        input, select, button { width: 100%; padding: 10px; margin-top: 5px; }
        button { background-color: #28a745; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #218838; }
    </style>
</head>
<body>

    <h2>Purchase a Book</h2>
    <form action="" method="POST">
        <label for="admission_no">Admission Number:</label>
        <input type="text" id="admission_no" name="admission_no" required>

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

        <button type="submit">Submit Purchase</button>
    </form>

</body>
</html>

<?php $conn->close(); ?>
