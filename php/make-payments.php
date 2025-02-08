<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make a Payment</title>
    <link rel="stylesheet" href="../style/style.css">
</head>
<body>

<div class="heading-all">
        <h2 class="title">Kayron Junior School</h2>
    </div>
    <div class="add-heading">
        <h2>Make Payments</h2>
    </div>

<div class="lunch-form">
    <form id="paymentForm" action="" method="POST">
    
    <?php
    session_start();
    if (isset($_SESSION['message'])){
        echo"<div class='message'>" . $_SESSION['message'] . "</div>";
        unset($_SESSION['message']);
    }
    ?>
        <div class="form-group">
            <label for="admission_no">Admission Number:</label>
            <input type="text" id="admission_no" name="admission_no" placeholder="Enter admission number" required>
        </div>
        <div class="form-group">
                <label for="name"><i class='bx bxs-user-circle'></i> Name</label>
                <input type="text" name="name" placeholder="Full Name" required>
            </div>

        <div class="form-group">
        <label for="fee_type">Select Fee Type:</label>
        <select id="fee_type" name="fee_type" onchange="updateFormAction()" required>
            <option value="">Select type</option>
            <option value="school_fees">School Fees</option>
            <option value="lunch_fees">Lunch Fees</option>
            <option value="admission_fee">Admission Fee</option>
            <option value="school_diary">School Diary</option>
        </select>
        </div>

        <div class="form-group">
            <label for="amount_paid">Amount (KSH):</label>
            <input type="number" id="amount_paid" name="amount_paid" placeholder="Enter amount" required>
        </div>

        <div class="form-group">
            <label for="payment_type">Payment Method:</label>
        <select id="payment_type" name="payment_type" required>
            <option value="">Select Method</option>
            <option value="mpesa">M-Pesa</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="cash">Cash</option>
        </select>
        </div>

        <div class="button-container">
            <button type="submit" class="add-student-btn">Proceed to Pay</button>
            <button type="button" class="add-student-btn"><a href="./dashboard.php">Back to dashboard</a></button>
        </div>
    </form>
</div>

<!--<div class="back-dash">
        <a href="./dashboard.php">Back to dashboard</a>
    </div>
</body>-->

<footer class="footer">
        <p>&copy; <?php echo date("Y")?>Kayron Junior School. All Rights Reserved.</p>
    </footer>
<script src="../js/script.js"></script>
</html>
