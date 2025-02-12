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
    
    <!-- Message Container for displaying feedback -->
    <div class="message-container"></div>

    <form id="paymentForm" action="" method="POST">
    <div id="messageBox"></div>

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
            <button type="submit" id="submitBtn" class="add-student-btn">Proceed to Pay</button>
            <button type="button" class="add-student-btn"><a href="./dashboard.php">Back to Dashboard</a></button>
        </div>
    </form>
</div> <!-- CLOSE .lunch-form PROPERLY -->

<!-- Footer -->
<footer class="footer-dash">
    <p>&copy; <?php echo date("Y")?> Kayron Junior School. All Rights Reserved.</p>
</footer>

<script>
$(document).ready(function () {
    $("input[type='checkbox']").on("change", function () {
        let amountInput = $(this).closest(".fee-item").find(".fee-amount");
        amountInput.prop("disabled", !$(this).is(":checked"));
        if (!$(this).is(":checked")) amountInput.val("");
        updateTotal();
    });

    $(".fee-amount").on("input", updateTotal);

    function updateTotal() {
        let totalPrice = 0;
        $(".fee-amount").each(function () {
            let value = parseFloat($(this).val());
            if (!isNaN(value) && $(this).prop("disabled") === false) {
                totalPrice += value;
            }
        });
        $("#total_price").text("Total Fee: KES " + totalPrice.toFixed(2));
    }

    $("#paymentForm").on("submit", async function (e) {
        e.preventDefault();

        let admission_no = $("#admission_no").val().trim();
        let payment_type = $("#payment_type").val();
        let receipt_number = generateReceiptNumber();
        let messageBox = $("#messageBox");
        let submitBtn = $("#submitBtn");

        if (!admission_no) {
            messageBox.html(`<div class="error-message">⚠️ Please enter a valid Admission Number.</div>`);
            return;
        }

        // Step 1: Validate Admission Number before proceeding
        try {
            let validationResponse = await $.post("validate-admission.php", { admission_no });
            let validationResult = JSON.parse(validationResponse);

            if (validationResult.status !== "success") {
                messageBox.html(`<div class="error-message">${validationResult.message}</div>`);
                return;
            }
        } catch (err) {
            console.error("Validation Error:", err);
            messageBox.html(`<div class="error-message">❌ Error verifying admission number.</div>`);
            return;
        }

        // Step 2: Collect selected payments
        let payments = [];
        let requests = [];
        let failedPayments = [];

        $("input[type='checkbox']:checked").each(function () {
            let feeType = $(this).val();
            let amount = parseFloat($(this).closest(".fee-item").find(".fee-amount").val().trim());

            if (!isNaN(amount) && amount > 0) {
                let paymentData = { admission_no, payment_type, fee_type: feeType, amount, receipt_number };
                payments.push(paymentData);

                let url = (feeType === "school_fees") ? "school-fee-payment.php" :
                          (feeType === "lunch_fees") ? "lunch-fee.php" : "others.php";

                requests.push(
                    $.post(url, paymentData).fail(() => {
                        failedPayments.push(feeType);
                    })
                );
            }
        });

        if (payments.length === 0) {
            messageBox.html(`<div class="error-message">⚠️ Please select at least one fee and enter a valid amount.</div>`);
            return;
        }

        submitBtn.prop("disabled", true).text("Processing...");

        setTimeout(async function () {
            try {
                await Promise.all(requests);

                if (failedPayments.length > 0) {
                    messageBox.html(`<div class="warning-message">⚠️ Some payments failed: ${failedPayments.join(", ")}. Please retry.</div>`);
                    submitBtn.prop("disabled", false).text("Retry Failed Payments");
                } else {
                    messageBox.html(`<div class="success-message">✅ Payment successful!</div>`);
                    submitBtn.text("Paid");
                }
            } catch (err) {
                console.error("Payment Error:", err);
                messageBox.html(`<div class="error-message">❌ An error occurred while processing payment.</div>`);
                submitBtn.prop("disabled", false).text("Proceed to Pay");
            }
        }, 2000);
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
