<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
include './db.php';

// Get current term number (e.g., T1)
$currentTerm = "T1";
$termQuery = $conn->query("SELECT term_number FROM terms ORDER BY id DESC LIMIT 1");
if ($termRow = $termQuery->fetch_assoc()) {
    $currentTerm = "T" . $termRow['term_number'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Make a Payment</title>
  <link rel="stylesheet" href="../style/style.css"/>
  <link rel="website icon" type="png" href="photos/Logo.jpg"/>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'/>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
.container-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  min-height: 100vh;
  padding: 20px;
  background-color: #f2f2f2;
  overflow-y: auto;
}

.container-wrapper .payment-container {
  width: 100%;
  max-width: 600px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
  padding: 25px 20px;
  margin-bottom: 30px;
}

.container-wrapper .payment-container h2 {
  text-align: center;
  margin-bottom: 20px;
  color: #333;
}

.container-wrapper .payment-container .form-group {
  position: relative;
  width: 100%;
  max-width: 400px;
  margin: 0 auto 20px;
}

.container-wrapper .payment-container .form-group label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  color: #333;
}

.container-wrapper .payment-container .form-group input[type="text"],
.container-wrapper .payment-container .form-group select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 16px;
  box-sizing: border-box;
}

.container-wrapper .payment-container .form-group #suggestions {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background-color: #007bff;
  max-height: 250px;
  overflow-y: auto;
  z-index: 1000;
  border-radius: 0 0 6px 6px;
}

.container-wrapper .payment-container .form-group .suggestion-item {
  padding: 10px 12px;
  cursor: pointer;
  font-size: 15px;
  color: #fff;
}

.container-wrapper .payment-container .form-group .suggestion-item:last-child {
  border-bottom: none;
}

.container-wrapper .payment-container .form-group .suggestion-item:hover {
  background-color: #fff;
  color: #007bff;
}

.container-wrapper .payment-container .fees-list {
  margin-bottom: 20px;
}

.container-wrapper .payment-container .fees-list label {
  font-weight: bold;
  font-size: 18px;
  display: block;
  margin-bottom: 12px;
  color: #333;
}

.container-wrapper .payment-container .fee-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fefefe;
  padding: 12px 15px;
  border-radius: 8px;
  box-shadow: 0px 2px 6px rgba(0, 0, 0, 0.08);
  margin-bottom: 12px;
  transition: all 0.3s ease;
}

.container-wrapper .payment-container .fee-item:hover {
  transform: translateY(-2px);
  box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.15);
}

.container-wrapper .payment-container .fee-item input[type="checkbox"] {
  margin-right: 12px;
  width: 20px;
  height: 20px;
  accent-color: #007bff;
  cursor: pointer;
}

.container-wrapper .payment-container .fee-item label {
  flex: 1;
  font-size: 16px;
  color: #333;
  margin-right: 10px;
}

.container-wrapper .payment-container .fee-item .fee-amount {
  width: 150px;
  padding: 6px 10px;
  font-size: 14px;
  border: 1px solid #ccc;
  border-radius: 6px;
  outline: none;
  transition: 0.3s ease;
}

.container-wrapper .payment-container .fee-item .fee-amount:focus {
  border-color: #007bff;
  box-shadow: 0 0 6px rgba(0, 123, 255, 0.4);
}

.container-wrapper .payment-container .total-container {
  text-align: center;
  margin-top: 18px;
  font-size: 18px;
  font-weight: bold;
  color: #007bff;
}

.container-wrapper .payment-container .button-container {
  margin-top: 20px;
}

.container-wrapper .payment-container .make-payments-btn {
  width: 100%;
  padding: 12px;
  font-size: 16px;
  background-color: #007bff;
  color: #fff;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

.container-wrapper .payment-container .make-payments-btn:hover {
  background-color: #0056b3;
}

/* Responsive Design */
@media (max-width: 768px) {
  .container-wrapper .payment-container {
    width: 90%;
    padding: 20px 15px;
  }

  .container-wrapper .payment-container .fee-item {
    flex-direction: column;
    align-items: flex-start;
  }

  .container-wrapper .payment-container .fee-item .fee-amount {
    width: 100%;
    margin-top: 8px;
  }
}
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="dashboard-container">
  <?php include '../includes/sidebar.php'; ?>
  <main class="content">
    <div class="container-wrapper">
      <div class="payment-container">
        <h2>Make a Payment</h2>

        <form id="paymentForm" method="POST">
          <div class="form-group">
            <input type="text" id="student-search" placeholder="Enter Student name..." autocomplete="off" />
            <input type="hidden" id="admission_no" name="admission_no" />
            <div id="suggestions"></div>
          </div>

          <div class="fees-list">
            <label>Select Fees:</label>

            <?php
            $fees = [
              "school_fees" => "School Fees",
              "lunch_fees" => "Lunch Fees",
              "admission" => "Admission Fee",
              "activity" => "Activity Fee",
              "exam" => "Exam Fee",
              "interview" => "Interview Fee"
            ];
            foreach ($fees as $value => $label) {
              echo "
              <div class='fee-item'>
                <input type='checkbox' id='{$value}_fee' name='fees[]' value='{$value}'>
                <label for='{$value}_fee'>{$label}</label>
                <input type='number' class='fee-amount' name='amount_{$value}' placeholder='Enter amount' disabled>
              </div>
              ";
            }
            ?>
            <div id="admission_message" style="margin-top: 10px;"></div>
          </div>

          <div class="total-container">
            <p id="total_price">Total Fee: KES 0.00</p>
          </div>

          <div class="form-group">
            <label for="payment_type">Payment Method:</label>
            <select id="payment_type" name="payment_type" required>
              <option value="">-- Select Method --</option>
              <option value="mpesa">M-Pesa</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="cash">Cash</option>
            </select>
          </div>

          <div class="button-container">
            <button type="submit" id="submitBtn" class="make-payments-btn">
              <i class='bx bx-cart-add'></i> Make Payment(s)
            </button>
          </div>

          <div id="messageBox" style="margin-top: 10px;"></div>
        </form>
      </div>
    </div>
  </main>
</div>

<?php include '../includes/footer.php'; ?>

<script>
const currentTerm = "<?php echo $currentTerm; ?>";

$(document).ready(function () {
  // === 1. Auto-Suggest Student ===
  $('#student-search').on('input', function () {
    const query = $(this).val().trim();
    if (!query) return $('#suggestions').empty();

    $.post("search-students.php", { query }, function (response) {
      $('#suggestions').empty();
      if (Array.isArray(response) && response.length > 0) {
        response.forEach(student => {
          $('#suggestions').append(`
            <div class="suggestion-item" data-admission="${student.admission_no}" data-name="${student.name}" data-class="${student.class}">
              ${student.name} - ${student.admission_no} - ${student.class}
            </div>
          `);
        });
      } else {
        $('#suggestions').append('<div class="suggestion-item">No records found</div>');
      }
    }, 'json');
  });

  $('#suggestions').on('click', '.suggestion-item', function () {
    const name = $(this).data('name');
    const admission = $(this).data('admission');
    const studentClass = $(this).data('class');
    $('#student-search').val(`${name} - ${admission} - ${studentClass}`);
    $('#admission_no').val(admission);
    $('#suggestions').empty();
  });

  $(document).on('click', function (e) {
    if (!$(e.target).closest('.form-group').length) $('#suggestions').empty();
  });

  // === 2. Fee Checkbox Toggle ===
  $("input[type='checkbox']").on("change", function () {
    const input = $(this).closest(".fee-item").find(".fee-amount");
    input.prop("disabled", !$(this).is(":checked"));
    if (!$(this).is(":checked")) input.val("");
    updateTotal();
  });

  // === 3. Admission Fee Check ===
  $("#admission_no").on("blur", function () {
    let admission_no = $(this).val().trim();
    if (!admission_no) return;
    $.post("check-admission-fee.php", { admission_no }, function (response) {
      const result = JSON.parse(response);
      if (result.status === "paid") {
        $("#admission_fee").prop("disabled", true).prop("checked", false);
        $("input[name='amount_admission']").prop("disabled", true).val("");
        $("#admission_message").html(`<span style="color: green;">✔ Admission fee already paid.</span>`);
      } else {
        $("#admission_fee").prop("disabled", false);
        $("#admission_message").html("");
      }
    }).fail(() => {
      $("#admission_message").html(`<span style="color: red;">Error checking admission fee status.</span>`);
    });
  });

  // === 4. Update Total ===
  $(".fee-amount").on("input", updateTotal);
  function updateTotal() {
    let total = 0;
    $(".fee-amount").each(function () {
      const val = parseFloat($(this).val());
      if (!isNaN(val) && !$(this).prop("disabled")) total += val;
    });
    $("#total_price").text("Total Fee: KES " + total.toFixed(2));
  }

  // === 5. Submit Payment Form ===
  $("#paymentForm").on("submit", function (e) {
    e.preventDefault();
    const admission_no = $("#admission_no").val().trim();
    const payment_type = $("#payment_type").val();
    const receipt_number = generateKJSReceipt();
    const messageBox = $("#messageBox");
    const submitBtn = $("#submitBtn");

    if (!admission_no) {
      messageBox.html(`<div class="error-message">Please enter a valid Admission Number.</div>`);
      return;
    }

    $.post("validate-admission.php", { admission_no }, function (response) {
      const result = JSON.parse(response);
      if (result.status !== "success") {
        messageBox.html(`<div class="error-message">${result.message}</div>`);
        return;
      }

      let fees = {};
      let requests = [];
      let failedPayments = [];

      $("input[type='checkbox']:checked").each(function () {
        let feeType = $(this).val();
        let amount = parseFloat($(this).closest(".fee-item").find(".fee-amount").val());
        if (!isNaN(amount) && amount > 0) {
          fees[feeType] = amount;
          let url = (feeType === "school_fees") ? "school-fee-payment.php" :
                    (feeType === "lunch_fees") ? "lunch-fee.php" : "others.php";
          requests.push(
            $.post(url, {
              admission_no, payment_type, fee_type: feeType, amount, receipt_number
            }).fail(() => failedPayments.push(feeType))
          );
        }
      });

      if (Object.keys(fees).length === 0) {
        messageBox.html(`<div class="error-message">Select at least one fee and amount.</div>`);
        return;
      }

      submitBtn.css("background-color", "orange").text("Processing...").prop("disabled", true);

      Promise.all(requests).then(() => {
        if (failedPayments.length > 0) {
          messageBox.html(`<div class="warning-message">Some payments failed: ${failedPayments.join(", ")}</div>`);
          submitBtn.css("background-color", "red").text("Retry").prop("disabled", false);
        } else {
          messageBox.html(`<div class="success-message">Payment successful! Opening receipt...</div>`);
          submitBtn.css("background-color", "green").text("Paid");

          const receiptData = {
            receipt_number,
            admission_no,
            payment_type,
            fees: JSON.stringify(fees),
            total: $("#total_price").text().replace("Total Fee: KES ", "")
          };
          const query = new URLSearchParams(receiptData).toString();

          setTimeout(() => {
            const receiptWindow = window.open("receipt.php?" + query, "_blank", "width=800,height=600");
            if (receiptWindow) {
              receiptWindow.focus();
              // Refresh current page after small delay
              setTimeout(() => {
                location.reload();
              }, 1000); // give user a moment to see the receipt opened
            } else {
              alert("Popup blocked. Please allow popups for this site.");
            }
          }, 2000);
        }
      });
    });
  });

  function generateKJSReceipt() {
    const random = Math.random().toString(36).substr(2, 5).toUpperCase();
    return `KJS-${currentTerm}-${random}`;
  }
});
</script>

</body>
</html>
