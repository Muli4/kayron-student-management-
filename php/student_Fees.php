<?php
// Database connection details
$servername = "localhost";
$username = "root"; // Change if different
$password = ""; // Change if set
$dbname = "school_database"; // Change to your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to generate a unique receipt number
function generateReceiptNumber($conn) {
    $receipt_number = "REC" . date("Ymd") . rand(1000, 9999); // Example: REC202402101234
    // Ensure it's unique in the database
    $stmt = $conn->prepare("SELECT id FROM others WHERE receipt_number = ?");
    $stmt->bind_param("s", $receipt_number);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return generateReceiptNumber($conn); // Retry if duplicate
    }
    $stmt->close();
    return $receipt_number;
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $receipt_number = generateReceiptNumber($conn);
    $admission_no = $_POST['admission_no'];
    $payment_date = $_POST['payment_date'];

    // Retrieve student name & term
    $sql = "SELECT name, term FROM student_records WHERE admission_no = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($name, $term);
        $stmt->fetch();
    } else {
        echo "Error: Admission number not found!";
        exit;
    }
    $stmt->close();

    // Loop through multiple fee types and insert each payment
    foreach ($_POST['fee_type'] as $index => $fee_type) {
        $amount = $_POST['amount'][$index]; // Get corresponding amount
        $is_recurring = isset($_POST['is_recurring'][$index]) ? 1 : 0; // Checkbox handling

        // Insert into `others` table
        $sql = "INSERT INTO others (receipt_number, admission_no, name, fee_type, amount, payment_date, term, is_recurring) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssdsis", $receipt_number, $admission_no, $name, $fee_type, $amount, $payment_date, $term, $is_recurring);

        if (!$stmt->execute()) {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    }

    echo "Fees recorded successfully!<br>Generated Receipt Number: <b>$receipt_number</b>";
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multiple Fees Payment</title>
</head>
<body>
    <h2>Enter Multiple Fee Payment Details</h2>
    <form method="POST" action="">
        <label>Admission Number:</label>
        <input type="text" name="admission_no" required><br><br>

        <label>Payment Date:</label>
        <input type="date" name="payment_date" required><br><br>

        <div id="fee-section">
            <div class="fee-entry">
                <label>Fee Type:</label>
                <select name="fee_type[]" required>
                    <option value="Admission">Admission</option>
                    <option value="Activity">Activity</option>
                    <option value="Exam">Exam</option>
                    <option value="Interview">Interview</option>
                </select>

                <label>Amount (KSH):</label>
                <input type="number" name="amount[]" step="0.01" required>

                <label>Recurring Payment:</label>
                <input type="checkbox" name="is_recurring[]">
                <button type="button" onclick="removeFee(this)">Remove</button>
                <br><br>
            </div>
        </div>

        <button type="button" onclick="addFee()">Add Another Fee</button><br><br>
        <button type="submit">Submit Payment</button>
    </form>

    <script>
        function addFee() {
            const feeSection = document.getElementById('fee-section');
            const newEntry = document.createElement('div');
            newEntry.classList.add('fee-entry');
            newEntry.innerHTML = `
                <label>Fee Type:</label>
                <select name="fee_type[]" required>
                    <option value="Admission">Admission</option>
                    <option value="Activity">Activity</option>
                    <option value="Exam">Exam</option>
                    <option value="Interview">Interview</option>
                </select>

                <label>Amount (KSH):</label>
                <input type="number" name="amount[]" step="0.01" required>

                <label>Recurring Payment:</label>
                <input type="checkbox" name="is_recurring[]">
                <button type="button" onclick="removeFee(this)">Remove</button>
                <br><br>
            `;
            feeSection.appendChild(newEntry);
        }

        function removeFee(button) {
            button.parentElement.remove();
        }
    </script>
</body>
</html>
