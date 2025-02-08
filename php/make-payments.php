<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make a Payment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            text-align: center;
            padding: 20px;
        }
        .container {
            max-width: 400px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        input, select, button {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        button {
            background-color: #28a745;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Make a Payment</h2>
    
    <form id="paymentForm" action="" method="POST">
    <?php
            if (isset($_SESSION['message'])) {
                echo $_SESSION['message'];
                unset($_SESSION['message']);
            }
            ?>
        <label for="admission_no">Admission Number:</label>
        <input type="text" id="admission_no" name="admission_no" placeholder="Enter your admission number" required>

        <label for="fee_type">Select Fee Type:</label>
        <select id="fee_type" name="fee_type" onchange="updateFormAction()">
            <option value="school_fees">School Fees</option>
            <option value="lunch_fees">Lunch Fees</option>
        </select>

        <label for="amount_paid">Amount (KSH):</label>
        <input type="number" id="amount_paid" name="amount_paid" placeholder="Enter amount" required>

        <label for="payment_type">Payment Method:</label>
        <select id="payment_type" name="payment_type">
            <option value="mpesa">M-Pesa</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="cash">Cash</option>
        </select>

        <button type="submit">Proceed to Pay</button>
    </form>
</div>

<script>
    function updateFormAction() {
        let feeType = document.getElementById("fee_type").value;
        let form = document.getElementById("paymentForm");

        if (feeType === "lunch_fees") {
            form.action = "lunch-fee.php";
        } else {
            form.action = "school-fee-payment.php";
        }
    }

    // Set initial form action
    updateFormAction();
</script>

</body>
</html>
