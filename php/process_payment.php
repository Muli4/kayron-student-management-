<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = $_POST['admission_no'];
    $payment_type = $_POST['payment_type'];
    $fees = $_POST['fees'];
    $receipt_number = "REC" . date("Ymd") . strtoupper(substr(md5(rand()), 0, 5));

    $total_amount = 0;
    $fee_details = [];

    foreach ($fees as $fee) {
        $amount_key = "amount_" . $fee;
        if (isset($_POST[$amount_key]) && is_numeric($_POST[$amount_key])) {
            $amount = $_POST[$amount_key];
            $total_amount += $amount;
            $fee_details[$fee] = $amount;
        }
    }

    // Store payment details in the database (Mock example)
    // Redirect to receipt page with details
    $query_string = http_build_query([
        "receipt_number" => $receipt_number,
        "admission_no" => $admission_no,
        "payment_type" => $payment_type,
        "fees" => json_encode($fee_details),
        "total" => $total_amount
    ]);

    header("Location: receipt.php?$query_string");
    exit();
}
?>


<script>
document.addEventListener("DOMContentLoaded", function () {
  /* ===== Real-time Clock ===== */
  function updateClock() {
    const clockElement = document.getElementById('realTimeClock');
    if (clockElement) {
      const now = new Date();
      clockElement.textContent = now.toLocaleTimeString();
    }
  }
  updateClock();
  setInterval(updateClock, 1000);

  /* ===== Dropdowns: only one open ===== */
  document.querySelectorAll(".dropdown-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      const parent = btn.parentElement;
      document.querySelectorAll(".dropdown").forEach(drop => {
        if (drop !== parent) drop.classList.remove("open");
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

  overlay.addEventListener('click', () => {
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
  });

  /* ===== Auto logout after 5 minutes ===== */
  let logoutTimer;
  function resetLogoutTimer() {
    clearTimeout(logoutTimer);
    logoutTimer = setTimeout(() => {
      window.location.href = 'logout.php';
    }, 300000); // 5 minutes
  }
  ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
    document.addEventListener(evt, resetLogoutTimer);
  });
  resetLogoutTimer();

  /* ===== Enable/Disable amount inputs based on checkbox ===== */
  document.querySelectorAll('.fee-item input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', function () {
      const amountInput = this.closest('.fee-item').querySelector('.fee-amount');
      amountInput.disabled = !this.checked;
      if (!this.checked) amountInput.value = '';
      updateTotal();
    });
  });

  /* ===== Update Total Amount ===== */
  function updateTotal() {
    let total = 0;
    document.querySelectorAll('.fee-amount:not(:disabled)').forEach(input => {
      total += parseFloat(input.value) || 0;
    });
    document.getElementById('totalAmount').textContent = total.toFixed(2);
  }

  document.querySelectorAll('.fee-amount').forEach(input => {
    input.addEventListener('input', updateTotal);
  });

  /* ===== jQuery Section ===== */
  $(document).ready(function () {
    // Live search
    $('#student-search').on('input', function () {
      const query = $(this).val().trim();
      if (query.length === 0) {
        $('#suggestions').empty();
        return;
      }

      $.ajax({
        url: 'search-students.php',
        method: 'POST',
        dataType: 'json',
        data: { query },
        success: function (response) {
          $('#suggestions').empty();
          if (Array.isArray(response) && response.length > 0) {
            response.forEach(function (student) {
              $('#suggestions').append(`
                <div class="suggestion-item" 
                     data-admission="${student.admission_no}" 
                     data-name="${student.name}" 
                     data-class="${student.class}">
                  ${student.name} - ${student.admission_no} - ${student.class}
                </div>
              `);
            });
          } else {
            $('#suggestions').append('<div class="suggestion-item">No records found</div>');
          }
        },
        error: function () {
          $('#suggestions').html('<div class="suggestion-item">Error fetching data</div>');
        }
      });
    });

    // Select student
    $('#suggestions').on('click', '.suggestion-item', function () {
      const name = $(this).data('name');
      const admission = $(this).data('admission');
      const studentClass = $(this).data('class');

      $('#student-search').val(`${name} - ${admission} - ${studentClass}`);
      $('#admission_no').val(admission);
      $('#student_name').val(name);
      $('#suggestions').empty();
    });

    // Close suggestions if clicking outside
    $(document).on('click', function (e) {
      if (!$(e.target).closest('.form-group').length) {
        $('#suggestions').empty();
      }
    });

    // AJAX form submission
    $('#feeForm').on('submit', function (e) {
      e.preventDefault();
      $('#message').text('');

      const admissionNo = $('#admission_no').val();
      const studentName = $('#student_name').val();
      const paymentType = $('select[name="payment_type"]').val();

      $.ajax({
        url: 'others.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function (res) {
          if (res.success) {
            $('#feeForm')[0].reset();
            $('#admission_no').val('');

            if (res.paid_details && res.paid_details.length > 0) {
              const date = new Date().toLocaleDateString();
              const total_paid_now = res.paid_details.reduce((sum, f) => sum + parseFloat(f.paid), 0);
              const total_balance = res.paid_details.reduce((sum, f) => {
                let bal = parseFloat(f.balance);
                return sum + (isNaN(bal) ? 0 : bal);
              }, 0);

              let receiptHtml = `
                <div class="receipt" style="font-family:Arial;padding:20px;width:450px;">
                  <div class="title" style="font-size:18px;font-weight:bold;">Kayron Junior School</div>
                  <div style="text-align:center;">Tel: 0703373151 / 0740047243</div>
                  <hr>
                  <strong>Official Receipt</strong><br>
                  Date: <strong>${date}</strong><br>
                  Receipt No: <strong>${res.receipt_number}</strong><br>
                  Admission No: <strong>${admissionNo}</strong><br>
                  Student Name: <strong>${studentName}</strong><br>
                  Payment Type: <strong>${paymentType}</strong>
                  <hr>
                  <strong>Fee Payments</strong>
                  <table border="1" cellspacing="0" cellpadding="5" style="width:100%;margin-top:10px;">
                    <tr>
                      <th>Fee Type</th>
                      <th>Paid Now (KES)</th>
                      <th>Balance (KES)</th>
                    </tr>`;

              res.paid_details.forEach(fee => {
                receiptHtml += `
                    <tr>
                      <td>${fee.fee_type}</td>
                      <td style="text-align:right;">${parseFloat(fee.paid).toFixed(2)}</td>
                      <td style="text-align:right;">${(fee.balance === '' || fee.balance == null) ? '' : parseFloat(fee.balance).toFixed(2)}</td>
                    </tr>`;
              });

              receiptHtml += `
                  </table>
                  <div style="margin-top:10px;font-weight:bold;">TOTAL PAID NOW: KES ${total_paid_now.toFixed(2)}</div>
                  <div style="margin-top:5px;font-weight:bold;">TOTAL BALANCE: KES ${total_balance.toFixed(2)}</div>
                  <div style="margin-top:10px;">Thank you for trusting in our school. Always working to output the best!</div>
                </div>
              `;

              const popup = window.open('', 'Receipt', 'width=500,height=600,scrollbars=yes');
              popup.document.write(`
                <html>
                  <head>
                    <title>Receipt</title>
                  </head>
                  <body>
                    ${receiptHtml}
                    <script>
                      window.onload = function() {
                        window.print();
                      };
                    <\/script>
                  </body>
                </html>
              `);
              popup.document.close();
            }
          } else {
            $('#message').text(res.message || 'An error occurred.');
          }
        },
        error: function () {
          $('#message').text('An unexpected error occurred.');
        }
      });
    });
  });
});
</script>

  <style>
.payment-container {
  max-width: 450px; 
  margin: 20px auto; 
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
.payment-message.success {
    color: green;
    margin-bottom: 10px;
}

.payment-message.error {
    color: red;
    margin-bottom: 10px;
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