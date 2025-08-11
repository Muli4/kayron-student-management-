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
    <link rel="stylesheet" href="../style/style-sheet.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
    <link rel="website icon" type="png" href="../images/school-logo.jpg">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
/* Container for the payment form */
.payment-container {
  max-width: 450px;          /* reduce overall width */
  margin: 20px auto;         /* center horizontally with vertical margin */
  padding: 20px;
  background-color: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
  font-family: Arial, sans-serif;
}

/* Heading */
.payment-container h2 {
  text-align: center;
  margin-bottom: 20px;
  font-weight: 700;
  color: #2c3e50;
}
.payment-container h2 i {
  margin-right: 8px;
  vertical-align: middle;
  color: #2980b9;
  font-size: 1.3em;
}

/* Form groups */
.form-group {
  margin-bottom: 15px;
  position: relative;
}

/* Student search input */
#student-search {
  width: 100%;
  padding: 10px 12px;
  font-size: 1rem;
  border: 1.8px solid #2980b9;
  border-radius: 5px;
}

/* Suggestions dropdown */
#suggestions {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #fff;
  border: 1.5px solid #2980b9;
  border-top: none;
  max-height: 150px;
  overflow-y: auto;
  z-index: 1000;
  font-size: 0.95rem;
}

.suggestion-item {
  padding: 8px 10px;
  cursor: pointer;
}

.suggestion-item:hover {
  background-color: #2980b9;
  color: white;
}

/* Fees list container */
.fees-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 10px;
}

/* Each fee item horizontally aligned */
.fee-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

/* Checkbox style */
.fee-item input[type="checkbox"] {
  transform: scale(1.15);
  cursor: pointer;
}

/* Label for fee */
.fee-item label {
  flex: 1;
  user-select: none;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
}

/* Amount input: bigger and more padding */
.fee-amount {
  width: 200px;
  padding: 8px 12px;
  font-size: 1.1rem;
  border: 1.8px solid #2980b9;
  border-radius: 5px;
  transition: background-color 0.3s ease;
}

/* Disabled amount input */
.fee-amount:disabled {
  background-color: #f5f5f5;
  cursor: not-allowed;
}

/* Total container */
.total-container {
  margin-top: 20px;
  font-weight: 700;
  font-size: 1.25rem;
  color: #2980b9;
  text-align: right;
}

/* Payment type select */
#payment_type {
  width: 100%;
  padding: 10px 12px;
  font-size: 1rem;
  border: 1.8px solid #2980b9;
  border-radius: 5px;
}

/* Submit button container */
.button-container {
  margin-top: 25px;
  text-align: center;
}

/* Submit button */
.make-payments-btn {
  background-color: #2980b9;
  color: #fff;
  font-weight: 700;
  font-size: 1.1rem;
  padding: 12px 20px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: background-color 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.make-payments-btn:hover {
  background-color: #1b6699;
}

/* Messages box */
#messageBox {
  font-size: 0.95rem;
  margin-top: 15px;
}

.error-message {
  color: #c0392b;
  font-weight: 600;
}

.success-message {
  color: #27ae60;
  font-weight: 600;
}

.warning-message {
  color: #f39c12;
  font-weight: 600;
}

/* ===== MEDIA QUERIES KEEPING SAME LAYOUT ===== */
@media (max-width: 600px) {
  .payment-container {
    max-width: 90%;
    padding: 15px;
  }

  .fee-amount {
    width: 140px;  /* smaller but still inline */
    font-size: 1rem;
  }

  .make-payments-btn {
    font-size: 1rem;
    padding: 10px 16px;
  }

  .fee-item {
    gap: 8px;  /* slightly less gap */
  }
}

@media (max-width: 400px) {
  .payment-container {
    max-width: 100%;
    margin: 10px 5px;
    padding: 10px;
  }

  .fee-amount {
    width: 120px; /* keep input smaller but inline */
    font-size: 0.95rem;
  }

  .make-payments-btn {
    width: 100%;
    justify-content: center;
    font-size: 0.95rem;
    padding: 10px;
  }

  #student-search, #payment_type {
    font-size: 0.95rem;
  }

  /* Keep fee items row layout */
  .fee-item {
    flex-direction: row;
    align-items: center;
    gap: 6px;
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
        <h2><i class='bx bx-credit-card'></i> Make a Payment</h2>

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
<script>
document.addEventListener("DOMContentLoaded", function () {
    /* ===== Real-time clock ===== */
    function updateClock() {
        const clockElement = document.getElementById('realTimeClock');
        if (clockElement) { // removed window.innerWidth check to show clock on all devices
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            clockElement.textContent = timeString;
        }
    }
    updateClock(); 
    setInterval(updateClock, 1000);

    /* ===== Dropdowns: only one open ===== */
    document.querySelectorAll(".dropdown-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            const parent = btn.parentElement;

            document.querySelectorAll(".dropdown").forEach(drop => {
                if (drop !== parent) {
                    drop.classList.remove("open");
                }
            });

            parent.classList.toggle("open");
        });
    });

    /* ===== Sidebar toggle for mobile ===== */
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.toggle-btn');
    const overlay = document.createElement('div');
    overlay.classList.add('sidebar-overlay');
    document.body.appendChild(overlay);

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    });

    /* ===== Close sidebar on outside click ===== */
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });

    /* ===== Auto logout after 30 seconds inactivity (no alert) ===== */
    let logoutTimer;

    function resetLogoutTimer() {
        clearTimeout(logoutTimer);
        logoutTimer = setTimeout(() => {
            // Silent logout - redirect to logout page
            window.location.href = 'logout.php'; // Change to your logout URL
        }, 300000); // 5 minutes
    }

    // Reset timer on user activity
    ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetLogoutTimer);
    });

    // Start the timer when page loads
    resetLogoutTimer();
});
</script>
</body>
</html>
