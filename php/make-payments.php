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

        <!-- Amount Paid -->
        <div class="form-group">
            <label for="amount_paid">Amount Paid (KSH):</label>
            <input type="number" id="amount_paid" name="amount_paid" placeholder="Enter amount" required>
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
        let amountInput = $(this).closest(".fee-item").find(".fee-amount"); // FIXED CLASS NAME
        if ($(this).is(":checked")) {
            amountInput.prop("disabled", false);
        } else {
            amountInput.prop("disabled", true).val(""); // Clear value if unchecked
        }
        updateTotal();
        updateFormAction();
    });

    // Update total whenever an amount is entered
    $(".fee-amount").on("input", function () {
        updateTotal();
    });

    function updateTotal() {
        let totalPrice = 0;
        $(".fee-amount").each(function () {
            if (!$(this).prop("disabled") && $(this).val() !== "") {
                totalPrice += parseFloat($(this).val());
            }
        });
        $("#total_price").text("Total Fee: KES " + totalPrice.toFixed(2)); // FIXED TEXT DISPLAY
    }

    function updateFormAction() {
        let form = $("#paymentForm");
        let schoolSelected = $("#school_fees").is(":checked");
        let lunchSelected = $("#lunch_fees").is(":checked");
        let othersSelected = $("#admission_fee").is(":checked") || $("#activity_fee").is(":checked") || $("#exam_fee").is(":checked") || $("#interview_fee").is(":checked");

        if (schoolSelected) {
            form.attr("action", "school-fee-payment.php");
        } else if (lunchSelected) {
            form.attr("action", "lunch-fee.php");
        } else if (othersSelected) {
            form.attr("action", "others.php");
        } else {
            form.attr("action", "make-payments.php"); // Default fallback
        }
    }
});
</script>

</body>
</html>
