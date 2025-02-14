<?php
include 'db.php'; // Include database connection

// Get the last recorded week number in lunch_fees
$stmt_max_week = $conn->prepare("SELECT MAX(week_number) AS max_week FROM lunch_fees");
$stmt_max_week->execute();
$max_week_result = $stmt_max_week->get_result();
$max_week_row = $max_week_result->fetch_assoc();
$max_week = $max_week_row['max_week'] ?? 52; // Default to 52 if no data

// Get the selected week number (default to last recorded week)
$week_number = isset($_GET['week_number']) ? (int)$_GET['week_number'] : $max_week;

// Fetch all students
$sql = "SELECT admission_no, name FROM student_records";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

$students = [];

while ($row = $result->fetch_assoc()) {
    $admission_no = $row['admission_no'];

    // Get balance from school_fees
    $stmt_fees = $conn->prepare("SELECT balance FROM school_fees WHERE admission_no = ?");
    $stmt_fees->bind_param("s", $admission_no);
    $stmt_fees->execute();
    $fees_result = $stmt_fees->get_result();
    $fees_balance = ($fees_row = $fees_result->fetch_assoc()) ? $fees_row['balance'] : 0;
    $fees_display = ($fees_balance > 0) ? $fees_balance : "Paid";

    // Get balance from lunch_fee filtered by week number
    $stmt_lunch = $conn->prepare("SELECT balance FROM lunch_fees WHERE admission_no = ? AND week_number = ?");
    $stmt_lunch->bind_param("si", $admission_no, $week_number);
    $stmt_lunch->execute();
    $lunch_result = $stmt_lunch->get_result();
    $lunch_balance = ($lunch_row = $lunch_result->fetch_assoc()) ? $lunch_row['balance'] : 0;
    $lunch_display = ($lunch_balance > 0) ? $lunch_balance : "Paid";

    // Get total balance from book_purchases
    $stmt_books = $conn->prepare("SELECT SUM(balance) AS total_balance FROM book_purchases WHERE admission_no = ?");
    $stmt_books->bind_param("s", $admission_no);
    $stmt_books->execute();
    $books_result = $stmt_books->get_result();
    $books_balance = ($books_row = $books_result->fetch_assoc()) ? $books_row['total_balance'] : 0;
    $books_display = ($books_balance > 0) ? $books_balance : "Paid";

    // Get total balance from uniform_purchases
    $stmt_uniform = $conn->prepare("SELECT SUM(balance) AS total_balance FROM uniform_purchases WHERE admission_no = ?");
    $stmt_uniform->bind_param("s", $admission_no);
    $stmt_uniform->execute();
    $uniform_result = $stmt_uniform->get_result();
    $uniform_balance = ($uniform_row = $uniform_result->fetch_assoc()) ? $uniform_row['total_balance'] : 0;
    $uniform_display = ($uniform_balance > 0) ? $uniform_balance : "Paid";

    // Calculate total balance
    $total_balance = $fees_balance + $lunch_balance + $books_balance + $uniform_balance;
    $total_display = ($total_balance > 0) ? $total_balance : "Paid";

    // Store student data
    $students[] = [
        'admission_no' => $admission_no,
        'name' => $row['name'],
        'school_fees' => $fees_display,
        'lunch_fee' => $lunch_display,
        'book_purchases' => $books_display,
        'uniform_purchases' => $uniform_display,
        'total_balance' => $total_display
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Balances</title>
    <link rel="stylesheet" href="../style/style.css">
</head>
<body>
<div class="heading-all">
    <h2 class="title">Kayron Junior School</h2>
</div>

<div class="container">
    <h2>Student Balances</h2>

    <!-- Week selection form -->
    <form method="GET" action="">
        <label for="week_number">Select Week Number:</label>
        <input type="number" id="week_number" name="week_number" min="1" max="<?= htmlspecialchars($max_week); ?>" value="<?= htmlspecialchars($week_number); ?>">
        <button type="submit">Filter</button>
    </form>

    <table border="1">
        <thead>
            <tr>
                <th>Admission No</th>
                <th>Name</th>
                <th>School Fees Balance</th>
                <th>Lunch Fee Balance (Week <?= htmlspecialchars($week_number); ?>)</th>
                <th>Books Balance</th>
                <th>Uniform Balance</th>
                <th>Total Balance</th> <!-- New Column -->
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $student): ?>
                <tr>
                    <td><?= htmlspecialchars($student['admission_no']); ?></td>
                    <td><?= htmlspecialchars($student['name']); ?></td>
                    <td><?= htmlspecialchars($student['school_fees']); ?></td>
                    <td><?= htmlspecialchars($student['lunch_fee']); ?></td>
                    <td><?= htmlspecialchars($student['book_purchases']); ?></td>
                    <td><?= htmlspecialchars($student['uniform_purchases']); ?></td>
                    <td><?= htmlspecialchars($student['total_balance']); ?></td> <!-- New Column -->
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="button-container">
    <button type="button" class="add-student-btn"><a href="./dashboard.php">Back to Dashboard</a></button>
</div>

<footer class="footer-dash">
    <p>&copy; <?= date("Y") ?> Kayron Junior School. All Rights Reserved.</p>
</footer>

<style>
/* Basic Styles */
.container {
    flex: 1;
    width: 80%;
    margin: 20px auto;
    font-family: Arial, sans-serif;
}
.container h2{
    text-align: center;
}
form {
    text-align: center;
    margin-bottom: 20px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
th, td {
    padding: 10px;
    text-align: left;
    border: 1px solid #ddd;
}
th {
    background: #007bff;
    color: white;
}
</style>
</body>
</html>
