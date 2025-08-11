<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

include 'db.php'; // Database connection
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Purchase Books & Uniform</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="../style/style-sheet.css">
    <link rel="website icon" type="png" href="../images/school-logo.jpg">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
  /* Container layout */
.purchase-form {
  max-width: 450px;          /* reduced width like school fees form */
  margin: 20px auto 50px;
  padding: 20px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
h2.form-title {
  text-align: center;
  font-weight: 700;
  font-size: 1.8rem;
  color: #2c3e50;
  margin-bottom: 30px;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 10px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

h2.form-title i {
  color: #2980b9;
  font-size: 1.6rem;
  vertical-align: middle;
}
/* Section titles with icon */
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 1.3rem;
  font-weight: 700;
  margin-bottom: 18px;
  color: #2c3e50;
  border-bottom: 2px solid #2980b9;
  padding-bottom: 5px;
}

/* Form row: align all items in one line */
.form-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 15px;
  flex-wrap: nowrap;
}

/* Label with checkbox: fixed width */
.checkbox-label {
  display: flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  flex: 1 0 160px;
  user-select: none;
  font-weight: 600;
  font-size: 1rem;
  color: #34495e;
}

/* Checkbox styling */
.checkbox-label input[type="checkbox"] {
  transform: scale(1.25);
  cursor: pointer;
  margin: 0;
}

/* Quantity input */
.form-quantity {
  width: 65px;
  padding: 6px 10px;
  font-size: 1rem;
  border: 1.8px solid #2980b9;
  border-radius: 5px;
  text-align: center;
}

/* Amount Paid input */
.form-amount-paid {
  width: 100px;
  padding: 6px 10px;
  font-size: 1rem;
  border: 1.8px solid #2980b9;
  border-radius: 5px;
  text-align: right;
}

/* Disabled inputs */
input:disabled {
  background-color: #f8f8f8;
  cursor: not-allowed;
  border-color: #ccc;
}

/* Student search wrapper */
.student-search-wrapper {
  position: relative;
}

/* Student search input full width */
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
  background: white;
  border: 1.5px solid #2980b9;
  border-top: none;
  width: 100%;
  max-height: 150px;
  overflow-y: auto;
  z-index: 1000;
  font-size: 0.95rem;
  border-radius: 0 0 6px 6px;
}

.suggestion-item {
  padding: 8px 10px;
  cursor: pointer;
  color: #34495e;
}

.suggestion-item:hover {
  background-color: #2980b9;
  color: white;
}
#total_amount {
  width: 100%;
  padding: 6px 10px;           /* Reduced vertical padding */
  font-size: 1rem;             /* Slightly smaller font size */
  font-weight: 700;
  color: #2980b9;
  border: 2px solid #2980b9;
  border-radius: 6px;
  background-color: #f0f8ff;
  text-align: right;
  pointer-events: none;        /* Makes it visually read-only */
  box-sizing: border-box;
}


/* Payment method select styling */
#payment_type {
  width: 100%;
  padding: 6px 10px;         /* Reduced vertical padding */
  font-size: 16px;            /* Optional: slightly smaller font */
  font-weight: 600;
  border: 2px solid #2980b9;
  border-radius: 6px;
  background-color: #fff;
  color: #2c3e50;
  box-sizing: border-box;
  cursor: pointer;
  transition: border-color 0.3s ease;
  height: auto;              /* Ensure height is not fixed */
}

#payment_type:focus {
  border-color: #1b6699;
  outline: none;
}

/* Button container and button */
.button-container {
  text-align: center;
  margin-top: 30px;
}

.submit-btn {
  background-color: #2980b9;
  color: white;
  font-weight: 700;
  font-size: 1.15rem;
  padding: 12px 25px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  transition: background-color 0.3s ease;
}

.submit-btn:hover {
  background-color: #1b6699;
}

/* Responsive adjustments */
@media (max-width: 550px) {
  .purchase-form {
    max-width: 95%;
    padding: 15px;
  }
  .form-row {
    flex-wrap: wrap;
    gap: 8px;
  }
  .checkbox-label {
    flex: 1 1 100%;
    margin-bottom: 6px;
  }
  .form-quantity, .form-amount-paid {
    width: 48%;
    text-align: left;
  }
  .form-row > label[for="student-search"] {
    flex: 0 0 100%; /* Label on its own line */
    margin-bottom: 6px;
  }

  .student-search-wrapper {
    flex: 1 1 100%;  /* Take full width */
  }

  #student-search {
    width: 100%;    /* Ensure input fills container */
  }
}

</style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>

    <main class="content">
        <h2 class="form-title"><i class='bx bx-cart'></i> Book & Uniform Purchase</h2>

        <?php
        include './db.php';
        $book_prices = $conn->query("SELECT * FROM book_prices");
        $uniform_prices = $conn->query("SELECT * FROM uniform_prices");
        ?>

        <form method="POST" action="purchase-handler.php" class="purchase-form" autocomplete="off">
            <!-- Student Lookup -->
            <section class="form-section">
                <h3 class="section-title"><i class='bx bxs-user-detail'></i> Student Info</h3>
                <div class="form-row">
                    <label for="student-search">Search Student *</label>
                    <div class="student-search-wrapper">
                        <input type="text" id="student-search" placeholder="Start typing student name..." required />
                        <div id="suggestions"></div>
                    </div>
                    <input type="hidden" id="admission_no" name="admission_no" required />
                </div>
            </section>

            <!-- Books -->
            <section class="form-section">
                <h3 class="section-title"><i class='bx bx-book-open'></i> Book Purchase</h3>
                <?php while ($book = $book_prices->fetch_assoc()): ?>
                <div class="form-row item-row book-item">
                    <label class="checkbox-label">
                        <input type="checkbox" name="books[<?= htmlspecialchars($book['book_name']) ?>][selected]" value="1" onchange="calculateTotal()" />
                        <span><?= htmlspecialchars($book['book_name']) ?> (KES <?= number_format($book['price'], 2) ?>)</span>
                    </label>
                    <input type="hidden" name="books[<?= htmlspecialchars($book['book_name']) ?>][price]" value="<?= $book['price'] ?>" />
                    <input type="number" name="books[<?= htmlspecialchars($book['book_name']) ?>][quantity]" placeholder="Qty" class="form-quantity" min="1" oninput="calculateTotal()" />
                    <input type="number" name="books[<?= htmlspecialchars($book['book_name']) ?>][amount_paid]" placeholder="Amount" class="form-amount-paid" min="0" step="0.01" oninput="calculateTotal()" />
                </div>
                <?php endwhile; ?>
            </section>

            <!-- Uniforms -->
            <section class="form-section">
                <h3 class="section-title"><i class='bx bx-t-shirt'></i> Uniform Purchase</h3>
                <?php while ($uniform = $uniform_prices->fetch_assoc()): ?>
                <div class="form-row item-row uniform-item">
                    <label class="checkbox-label">
                        <input type="checkbox" name="uniforms[<?= $uniform['id'] ?>][selected]" value="1" onchange="calculateTotal()" />
                        <span><?= htmlspecialchars($uniform['uniform_type']) ?> (Size <?= htmlspecialchars($uniform['size']) ?> - KES <?= number_format($uniform['price'], 2) ?>)</span>
                    </label>
                    <input type="hidden" name="uniforms[<?= $uniform['id'] ?>][type]" value="<?= htmlspecialchars($uniform['uniform_type']) ?>" />
                    <input type="hidden" name="uniforms[<?= $uniform['id'] ?>][size]" value="<?= htmlspecialchars($uniform['size']) ?>" />
                    <input type="hidden" name="uniforms[<?= $uniform['id'] ?>][price]" value="<?= $uniform['price'] ?>" />
                    <input type="number" name="uniforms[<?= $uniform['id'] ?>][quantity]" placeholder="Qty" class="form-quantity" min="1" oninput="calculateTotal()" />
                    <input type="number" name="uniforms[<?= $uniform['id'] ?>][amount_paid]" placeholder="Amount" class="form-amount-paid" min="0" step="0.01" oninput="calculateTotal()" />
                </div>
                <?php endwhile; ?>
            </section>

            <!-- Payment -->
            <section class="form-section">
                <h3 class="section-title"><i class='bx bx-money'></i> Payment</h3>
                <div class="form-row">
                    <label for="total_amount">Total Amount</label>
                    <input type="text" id="total_amount" name="total_amount" class="form-input" readonly />
                </div>
                <div class="form-row">
                    <label for="payment_type">Payment Method *</label>
                    <select id="payment_type" name="payment_type" class="form-input" required>
                        <option value="" selected disabled>-- Select --</option>
                        <option value="Cash">Cash</option>
                        <option value="M-Pesa">M-Pesa</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Card">Card</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </section>

            <div class="button-container">
                <button type="submit" class="submit-btn"><i class='bx bx-check-circle'></i> Submit Purchase</button>
            </div>
        </form>
    </main>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function () {
  // Live search with jQuery + AJAX POST (your existing code)
  $('#student-search').on('input', function () {
    const query = $(this).val().trim();
    $('#admission_no').val('');

    if (query.length < 1) {
      $('#suggestions').empty().hide();
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
          $('#suggestions').show();
        } else {
          $('#suggestions').append('<div class="suggestion-item">No records found</div>').show();
        }
      },
      error: function () {
        $('#suggestions').html('<div class="suggestion-item">Error fetching data</div>').show();
      }
    });
  });

  $('#suggestions').on('click', '.suggestion-item', function () {
    const name = $(this).data('name');
    const admission = $(this).data('admission');
    const studentClass = $(this).data('class');

    $('#student-search').val(`${name} - ${admission} - ${studentClass}`);
    $('#admission_no').val(admission);
    $('#suggestions').empty().hide();
  });

  $(document).on('click', function (e) {
    if (!$(e.target).closest('.student-search-wrapper').length) {
      $('#suggestions').empty().hide();
    }
  });

  // New: Manage enabling/disabling inputs based on checkbox state
  function updateInputsState() {
    $('.item-row').each(function () {
      const checkbox = $(this).find('input[type="checkbox"]');
      const isChecked = checkbox.is(':checked');
      $(this).find('input.form-quantity, input.form-amount-paid').prop('disabled', !isChecked);
      if (!isChecked) {
        $(this).find('input.form-quantity, input.form-amount-paid').val('');
      }
    });
  }

  // On checkbox change, update inputs and recalculate total
  $('.item-row input[type="checkbox"]').on('change', function () {
    updateInputsState();
    calculateTotal();
  });

  // On quantity or amount_paid input change, recalculate total
  $('.item-row input.form-quantity, .item-row input.form-amount-paid').on('input', function () {
    calculateTotal();
  });

  // Initialize inputs state on page load
  updateInputsState();

});

// Calculate total amount paid for checked items
function calculateTotal() {
  let totalPaid = 0;

  document.querySelectorAll('.item-row').forEach(item => {
    const checkbox = item.querySelector('input[type="checkbox"]');
    const amountPaidInput = item.querySelector('input.form-amount-paid');

    if (checkbox && checkbox.checked && amountPaidInput) {
      const amountPaid = parseFloat(amountPaidInput.value);
      if (!isNaN(amountPaid)) {
        totalPaid += amountPaid;
      }
    }
  });

  document.getElementById('total_amount').value = totalPaid.toFixed(2);
}
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
