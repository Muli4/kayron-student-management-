<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make a Payment</title>
    <link rel="stylesheet" href="../style/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="heading-all">
    <h2 class="title">Kayron Junior School</h2>
</div>

<div class="lunch-form">
    <div class="add-heading">
        <h2>Make Payments</h2>
    </div>
    <form id="paymentForm" action="" method="POST">
    
        <?php
        session_start();
        if (isset($_SESSION['message'])){
            echo "<div class='message'>" . $_SESSION['message'] . "</div>";
            unset($_SESSION['message']);
        }
        ?>

        <!-- Admission Number Input -->
        <div class="form-group">
            <label for="admission_no">Admission Number:</label>
            <input type="text" id="admission_no" name="admission_no" placeholder="Enter admission number" required>
        </div>

        <!-- Fees Selection -->
        <div class="fees-list">
            <label>Select Fees:</label>

            <div class="fee-item">
                <input type="checkbox" id="school_fees" name="fees[]" value="school_fees">
                <label for="school_fees">School Fees</label>
                <input type="number" class="fee-amount" name="amount_school_fees" placeholder="Enter amount" disabled>
            </div>

            <div class="fee-item">
                <input type="checkbox" id="lunch_fees" name="fees[]" value="lunch_fees">
                <label for="lunch_fees">Lunch Fees</label>
                <input type="number" class="fee-amount" name="amount_lunch_fees" placeholder="Enter amount" disabled>
            </div>

            <div class="fee-item">
                <input type="checkbox" id="admission_fee" name="fees[]" value="admission">
                <label for="admission_fee">Admission Fee</label>
                <input type="number" class="fee-amount" name="amount_admission" placeholder="Enter amount" disabled>
            </div>

            <div class="fee-item">
                <input type="checkbox" id="activity_fee" name="fees[]" value="activity">
                <label for="activity_fee">Activity Fee</label>
                <input type="number" class="fee-amount" name="amount_activity" placeholder="Enter amount" disabled>
            </div>

            <div class="fee-item">
                <input type="checkbox" id="exam_fee" name="fees[]" value="exam">
                <label for="exam_fee">Exam Fee</label>
                <input type="number" class="fee-amount" name="amount_exam" placeholder="Enter amount" disabled>
            </div>

            <div class="fee-item">
                <input type="checkbox" id="interview_fee" name="fees[]" value="interview">
                <label for="interview_fee">Interview Fee</label>
                <input type="number" class="fee-amount" name="amount_interview" placeholder="Enter amount" disabled>
            </div>

            <div class="total-container">
                <p id="total_price">Total Fee: KES 0.00</p>
            </div>
        </div>

        <!-- Payment Method Selection -->
        <div class="form-group">
            <label for="payment_type">Payment Method:</label>
            <select id="payment_type" name="payment_type" required>
                <option value="">-- Select Method --</option>
                <option value="mpesa">M-Pesa</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cash">Cash</option>
            </select>
        </div>

        <!-- Action Buttons -->
        <div class="button-container">
            <button type="submit" class="add-student-btn">Purchase</button>
            <button type="button" class="add-student-btn"><a href="./dashboard.php">Back to Dashboard</a></button>
        </div>
    </form>
</div> <!-- CLOSE .lunch-form PROPERLY -->

<!-- Footer should be outside the form -->
<footer class="footer-dash">
    <p>&copy; <?php echo date("Y")?> Kayron Junior School. All Rights Reserved.</p>
</footer>

<script>
$(document).ready(function () {
    // Enable/disable amount input based on checkbox selection
    $("input[type='checkbox']").on("change", function () {
        let amountInput = $(this).closest(".fee-item").find(".fee-amount");
        if ($(this).is(":checked")) {
            amountInput.prop("disabled", false);
        } else {
            amountInput.prop("disabled", true).val(""); // Clear value if unchecked
        }
        updateTotal();
    });

    // Update total fee whenever an amount is entered
    $(".fee-amount").on("input", function () {
        updateTotal();
    });

    function updateTotal() {
        let totalPrice = 0;
        $(".fee-amount").each(function () {
            if (!$(this).prop("disabled") && $(this).val() !== "") {
                let value = parseFloat($(this).val());
                if (!isNaN(value)) {
                    totalPrice += value;
                }
            }
        });
        $("#total_price").text("Total Fee: KES " + totalPrice.toFixed(2));
    }

    $("#paymentForm").on("submit", async function (e) {
        e.preventDefault(); // Prevent normal form submission

        let admission_no = $("#admission_no").val().trim();
        let payment_type = $("#payment_type").val();
        let receipt_number = generateReceiptNumber();

        if (!admission_no) {
            alert("⚠️ Please enter a valid Admission Number.");
            return;
        }

        let requests = [];

        // Process each selected fee
        $("input[type='checkbox']:checked").each(function () {
            let feeType = $(this).val();
            let amountField = $(this).closest(".fee-item").find(".fee-amount").val().trim();
            let amount = parseFloat(amountField);

            if (!isNaN(amount) && amount > 0) {
                let paymentData = {
                    admission_no: admission_no,
                    payment_type: payment_type,
                    fee_type: feeType,
                    amount: amount,
                    receipt_number: receipt_number
                };

                let url = (feeType === "school_fees") ? "school-fee-payment.php" :
                          (feeType === "lunch_fees") ? "lunch-fee.php" :
                          "others.php"; // Process all other fees here

                requests.push($.post(url, paymentData));
            }
        });

        if (requests.length === 0) {
            alert("⚠️ Please select at least one fee and enter a valid amount.");
            return;
        }

        $("#submitBtn").prop("disabled", true).text("Processing...");

        try {
            await Promise.all(requests);
            alert("✅ Payment successful! Receipt Number: " + receipt_number);
            window.location.reload();
        } catch (err) {
            console.error("Payment Error:", err);
            alert("❌ An error occurred while processing payment.");
        } finally {
            $("#submitBtn").prop("disabled", false).text("Submit Payment");
        }
    });

    function generateReceiptNumber() {
        let datePart = new Date().toISOString().slice(0, 10).replace(/-/g, "");
        let randomPart = Math.random().toString(36).substr(2, 5).toUpperCase();
        return "REC" + datePart + randomPart;
    }
});

</script>


</body>
</html>
