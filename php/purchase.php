<?php
session_start();
include './db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admission_no = trim($_POST['admission_no'] ?? '');
    $payment_type = $_POST['payment_type'] ?? 'Cash';

    $valid_payment_types = ['Cash', 'Card', 'Bank Transfer', 'Other', 'M-Pesa'];
    if (!in_array($payment_type, $valid_payment_types)) {
        $payment_type = 'Other';
    }
    $uniform_payment_type = $payment_type === 'M-Pesa' ? 'Other' : $payment_type;

    if (empty($admission_no)) {
        die(json_encode(["status" => "error", "message" => "Missing admission number."]));
    }

    // 1. Get student
    $check = $conn->prepare("SELECT name FROM student_records WHERE admission_no = ?");
    $check->bind_param("s", $admission_no);
    $check->execute();
    $result = $check->get_result();
    $student = $result->fetch_assoc();
    $check->close();

    if (!$student) {
        die(json_encode(["status" => "error", "message" => "Admission number not found."]));
    }

    $student_name = $student['name'];

    // 2. Get latest term
    $term_result = $conn->query("SELECT term_number FROM terms ORDER BY id DESC LIMIT 1");
    $latest_term = $term_result->fetch_assoc();
    $term_number = $latest_term ? $latest_term['term_number'] : 'X';
    $receipt_number = "KJS-T{$term_number}-" . strtoupper(substr(uniqid(), -5));

    // 3. Helper
    function postVal($arr, $key, $default = '') {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    // 4. Book purchases
    if (!empty($_POST['books'])) {
        foreach ($_POST['books'] as $book_name => $book) {
            if (empty($book['selected'])) continue;

            $quantity = (int)postVal($book, 'quantity', 0);
            $price = (float)postVal($book, 'price', 0);
            $amount_paid = (float)postVal($book, 'amount_paid', 0);

            if ($quantity > 0 && !empty($book_name)) {
                // Check for existing balance
                $existing = $conn->prepare("SELECT id, amount_paid, total_price FROM book_purchases 
                                            WHERE admission_no = ? AND book_name = ? AND balance > 0 
                                            ORDER BY id DESC LIMIT 1");
                $existing->bind_param("ss", $admission_no, $book_name);
                $existing->execute();
                $existing_result = $existing->get_result();
                $row = $existing_result->fetch_assoc();
                $existing->close();

                if ($row) {
                    $old_paid = $row['amount_paid'];
                    $total_price = $row['total_price'];
                    $record_id = $row['id'];
                    $old_balance = $total_price - $old_paid;

                    $leftover = $amount_paid - $old_balance;

                    if ($leftover >= 0) {
                        // Update old record
                        $new_paid = $old_paid + $old_balance;
                        $update = $conn->prepare("UPDATE book_purchases SET amount_paid = ?, balance = 0 WHERE id = ?");
                        $update->bind_param("di", $new_paid, $record_id);
                        $update->execute();
                        $update->close();

                        // Use remaining for new
                        $new_total = $quantity * $price;
                        $new_balance = $new_total - $leftover;

                        $insert = $conn->prepare("INSERT INTO book_purchases 
                            (receipt_number, admission_no, name, book_name, quantity, total_price, amount_paid, balance, payment_type) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $insert->bind_param("ssssiddss", $receipt_number, $admission_no, $student_name, $book_name, $quantity, $new_total, $leftover, $new_balance, $payment_type);
                        $insert->execute();
                        $insert->close();
                    } else {
                        // Partial payment, just update old record
                        $new_paid = $old_paid + $amount_paid;
                        $new_balance = $total_price - $new_paid;

                        $update = $conn->prepare("UPDATE book_purchases SET amount_paid = ?, balance = ? WHERE id = ?");
                        $update->bind_param("ddi", $new_paid, $new_balance, $record_id);
                        $update->execute();
                        $update->close();
                    }
                } else {
                    // No old balance
                    $total = $quantity * $price;
                    $balance = $total - $amount_paid;

                    $insert = $conn->prepare("INSERT INTO book_purchases 
                        (receipt_number, admission_no, name, book_name, quantity, total_price, amount_paid, balance, payment_type) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insert->bind_param("ssssiddss", $receipt_number, $admission_no, $student_name, $book_name, $quantity, $total, $amount_paid, $balance, $payment_type);
                    $insert->execute();
                    $insert->close();
                }
            }
        }
    }

    // 5. Uniform purchases
    if (!empty($_POST['uniforms'])) {
        foreach ($_POST['uniforms'] as $uniform) {
            if (empty($uniform['selected'])) continue;

            $uniform_type = postVal($uniform, 'type');
            $size = postVal($uniform, 'size');
            $quantity = (int)postVal($uniform, 'quantity', 0);
            $price = (float)postVal($uniform, 'price', 0);
            $amount_paid = (float)postVal($uniform, 'amount_paid', 0);

            if ($quantity > 0 && !empty($uniform_type)) {
                // Check previous balance
                $existing = $conn->prepare("SELECT id, amount_paid, total_price FROM uniform_purchases 
                                            WHERE admission_no = ? AND uniform_type = ? AND balance > 0 
                                            ORDER BY id DESC LIMIT 1");
                $existing->bind_param("ss", $admission_no, $uniform_type);
                $existing->execute();
                $existing_result = $existing->get_result();
                $row = $existing_result->fetch_assoc();
                $existing->close();

                if ($row) {
                    $old_paid = $row['amount_paid'];
                    $total_price = $row['total_price'];
                    $record_id = $row['id'];
                    $old_balance = $total_price - $old_paid;

                    $leftover = $amount_paid - $old_balance;

                    if ($leftover >= 0) {
                        $new_paid = $old_paid + $old_balance;
                        $update = $conn->prepare("UPDATE uniform_purchases SET amount_paid = ?, balance = 0 WHERE id = ?");
                        $update->bind_param("di", $new_paid, $record_id);
                        $update->execute();
                        $update->close();

                        $new_total = $quantity * $price;
                        $new_balance = $new_total - $leftover;

                        $insert = $conn->prepare("INSERT INTO uniform_purchases 
                            (receipt_number, name, admission_no, uniform_type, size, quantity, total_price, amount_paid, balance, payment_type) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $insert->bind_param("sssssiddds", $receipt_number, $student_name, $admission_no, $uniform_type, $size, $quantity, $new_total, $leftover, $new_balance, $uniform_payment_type);
                        $insert->execute();
                        $insert->close();
                    } else {
                        $new_paid = $old_paid + $amount_paid;
                        $new_balance = $total_price - $new_paid;

                        $update = $conn->prepare("UPDATE uniform_purchases SET amount_paid = ?, balance = ? WHERE id = ?");
                        $update->bind_param("ddi", $new_paid, $new_balance, $record_id);
                        $update->execute();
                        $update->close();
                    }
                } else {
                    $total = $quantity * $price;
                    $balance = $total - $amount_paid;

                    $insert = $conn->prepare("INSERT INTO uniform_purchases 
                        (receipt_number, name, admission_no, uniform_type, size, quantity, total_price, amount_paid, balance, payment_type) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insert->bind_param("sssssiddds", $receipt_number, $student_name, $admission_no, $uniform_type, $size, $quantity, $total, $amount_paid, $balance, $uniform_payment_type);
                    $insert->execute();
                    $insert->close();
                }
            }
        }
    }

    echo json_encode([
        "status" => "success",
        "receipt_number" => $receipt_number,
        "student_name" => $student_name,
        "admission_no" => $admission_no
    ]);
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Purchase Books & Uniform</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="../style/style.css" />
    <link rel="website icon" href="photos/Logo.jpg" />
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
<style>
  /* Container row for each item */
.form-title {
  font-size: 2.2rem;
  color: #2c3e50;
  font-weight: 700;
  margin-bottom: 24px;
  text-align: center;
  text-transform: uppercase;
  letter-spacing: 1.2px;
  text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
}

.item-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

/* Checkbox + label wrapper */
.checkbox-label {
  display: flex;
  align-items: center;
  white-space: nowrap;  /* prevent label text wrap */
  gap: 8px;
  flex: 1 1 auto; /* take remaining horizontal space */
}

/* Checkbox input style */
.checkbox-label input[type="checkbox"] {
  margin: 0;
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  cursor: pointer;
}

/* Label text inside span */
.checkbox-label span {
  user-select: none;
}

/* Quantity input */
.form-quantity {
  width: 100px;
  padding: 6px 8px;
  box-sizing: border-box;
  font-size: 1rem;
}

/* Amount paid input */
.form-amount-paid {
  width: 160px;
  padding: 6px 8px;
  box-sizing: border-box;
  font-size: 1rem;
}

/* === FORM CONTAINER & BACKGROUND === */
.purchase-form {
  max-width: 650px;
  margin: 40px auto;
  padding: 30px 35px;
  background: #f9fafb;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
  border-radius: 12px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  font-size: 0.9rem;
  color: #2c3e50;
}

/* === FORM SECTION TITLES === */
.purchase-form .section-title {
  font-size: 1.3rem;
  font-weight: 700;
  color: #34495e;
  border-bottom: 2px solid #3498db;
  padding-bottom: 6px;
  margin-bottom: 18px;
  user-select: none;
}

/* === FORM ROWS (except .item-row) === */
.purchase-form > section .form-row:not(.item-row) {
  display: flex;
  align-items: center;
  margin-bottom: 16px;
  gap: 15px;
}

/* === LABELS (except inside .checkbox-label) === */
.purchase-form label:not(.checkbox-label) {
  min-width: 140px;
  font-weight: 600;
  font-size: 1rem;
  color: #34495e;
  user-select: none;
}

/* === TEXT, NUMBER INPUTS & SELECT (excluding .form-quantity & .form-amount-paid) === */
.purchase-form input[type="text"]:not(.form-quantity):not(.form-amount-paid),
.purchase-form input[type="number"]:not(.form-quantity):not(.form-amount-paid),
.purchase-form select {
  font-size: 0.9rem;
  padding: 8px 12px;
  box-sizing: border-box;
  border: 1.8px solid #bdc3c7;
  border-radius: 6px;
  max-width: 220px;
  transition: border-color 0.3s ease;
  background: #fff;
  color: #2c3e50;
}

.purchase-form input[type="text"]:focus,
.purchase-form input[type="number"]:focus,
.purchase-form select:focus {
  border-color: #2980b9;
  outline: none;
  background: #eaf4fc;
}

/* === SUBMIT BUTTON === */
.purchase-form .submit-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 1rem;
  padding: 12px 28px;
  background-color: #2980b9;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(41, 128, 185, 0.4);
  transition: background-color 0.3s ease, box-shadow 0.3s ease;
}

.purchase-form .submit-btn:hover {
  background-color: #1c5980;
  box-shadow: 0 6px 18px rgba(28, 89, 128, 0.6);
}

/* === SUGGESTIONS BOX FOR LIVE SEARCH === */
#suggestions {
  position: absolute;
  background: white;
  border: 1.5px solid #bdc3c7;
  max-width: 350px;
  max-height: 200px;
  overflow-y: auto;
  font-size: 0.9rem;
  color: #2c3e50;
  z-index: 9999;
  box-shadow: 0 5px 15px rgba(0,0,0,0.1);
  border-radius: 6px;
}

#suggestions .suggestion-item {
  padding: 8px 14px;
  cursor: pointer;
  user-select: none;
  transition: background-color 0.25s ease;
}

#suggestions .suggestion-item:hover {
  background-color: #2980b9;
  color: white;
}

/* === STUDENT SEARCH WRAPPER - relative for suggestions positioning === */
.student-search-wrapper {
  position: relative;
  width: 100%;
  max-width: 350px;
}

/* Style the select dropdown */
.form-row select#payment_type {
  width: 100%;
  max-width: 320px;
  padding: 8px 12px;
  border-radius: 5px;
  border: 1.8px solid #3498db;
  font-size: 1rem;
  background-color: #fff;
  appearance: none; /* Remove default arrow */
  -webkit-appearance: none;
  -moz-appearance: none;
  cursor: pointer;
  background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%233498db' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  background-size: 16px 16px;
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-row select#payment_type:focus {
  outline: none;
  border-color: #2980b9;
  box-shadow: 0 0 8px rgba(41, 128, 185, 0.4);
}

/* Style the dropdown options */
.form-row select#payment_type option {
  background-color: #f9f9f9;
  color: #2c3e50;
  padding: 8px;
  font-size: 1rem;
}

/* Hover/focus style for options (some browsers support it) */
.form-row select#payment_type option:hover,
.form-row select#payment_type option:focus {
  background-color: #3498db;
  color: white;
}

/* === RESPONSIVE: smaller screens === */
@media (max-width: 480px) {
  .purchase-form {
    padding: 20px 20px;
    margin: 20px 10px;
  }
  
  .purchase-form > section .form-row:not(.item-row) {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .purchase-form label:not(.checkbox-label) {
    min-width: auto;
    margin-bottom: 6px;
  }
  
  .form-quantity, .form-amount-paid {
    width: 100%;
    max-width: none;
  }
  
  #suggestions {
    max-width: 100%;
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

        <form method="POST" action="purchase.php" class="purchase-form" autocomplete="off">
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
                    <input type="number" name="books[<?= htmlspecialchars($book['book_name']) ?>][amount_paid]" placeholder="Amount Paid" class="form-amount-paid" min="0" step="0.01" oninput="calculateTotal()" />
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
                    <input type="number" name="uniforms[<?= $uniform['id'] ?>][amount_paid]" placeholder="Amount Paid" class="form-amount-paid" min="0" step="0.01" oninput="calculateTotal()" />
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

    if (query.length < 2) {
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

</body>
</html>
