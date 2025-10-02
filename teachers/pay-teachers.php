<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
include '../php/db.php';

// Handle form submission
if (isset($_POST['pay_teacher'])) {
    $teacher_id   = $_POST['teacher_id'];
    $amount       = $_POST['amount'];
    $allowance    = $_POST['allowance'] ?? 0;
    $deduction    = $_POST['deduction'] ?? 0;
    $pay_date     = $_POST['pay_date'];
    $remarks      = $_POST['remarks'];

    $net_pay = $amount + $allowance - $deduction;

    $stmt = $conn->prepare("INSERT INTO teacher_payments (teacher_id, amount, allowance, deduction, net_pay, pay_date, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("idddiss", $teacher_id, $amount, $allowance, $deduction, $net_pay, $pay_date, $remarks);

    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='message-success'>✅ Payment recorded successfully!</div>";
    } else {
        $_SESSION['message'] = "<div class='message-error'>❌ Error: ".$conn->error."</div>";
    }
    header("Location: pay-teachers.php?teacher_id=".$teacher_id);
    exit();
}

// Fetch teachers
$teachers = $conn->query("SELECT id, code, name FROM teacher_records ORDER BY name ASC");

// If teacher selected
$selected_teacher = null;
$payments = null;
if (isset($_GET['teacher_id'])) {
    $tid = intval($_GET['teacher_id']);
    $teacher_sql = $conn->prepare("SELECT * FROM teacher_records WHERE id=?");
    $teacher_sql->bind_param("i", $tid);
    $teacher_sql->execute();
    $selected_teacher = $teacher_sql->get_result()->fetch_assoc();

    $payments = $conn->query("SELECT * FROM teacher_payments WHERE teacher_id=$tid ORDER BY pay_date DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pay Teachers</title>
<link rel="stylesheet" href="../style/style-sheet.css">
<link rel="icon" type="image/png" href="../images/school-logo.jpg">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #004b8d;
  margin-bottom: 20px;
  text-align: center;
  border-bottom: 2px solid #004b8d;
  padding-bottom: 10px;
}
.form-box {
  background: #fff;
  padding: 1.5rem;
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  margin-bottom: 2rem;
}
.form-group {
  margin-bottom: 1rem;
  display: flex;
  flex-direction: column;
}
.form-group label {
  font-weight: 600;
  margin-bottom: .3rem;
}
.form-group input, .form-group select, .form-group textarea {
  padding: .6rem;
  border: 1.5px solid #4e73df;
  border-radius: 6px;
}
.save-btn {
  background: linear-gradient(135deg, #4e73df, #1cc88a);
  color: white;
  padding: 0.6rem 1.2rem;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
}
.save-btn:hover { opacity: 0.9; }
.table-container {
  background: #fff;
  padding: 1rem;
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  overflow-x: auto;
}
table { width: 100%; border-collapse: collapse; }
th, td { padding: 10px 15px; border-bottom: 1px solid #ddd; text-align: left; }
th { background: #004b8d; color: #fff; }
tr:hover { background: #f8f9fc; }
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="dashboard-container">
<?php include '../includes/sidebar.php'; ?>
<main class="content">

<h2 class="page-title"><i class='bx bx-money'></i> Pay Teachers</h2>

<?php if (isset($_SESSION['message'])) { echo $_SESSION['message']; unset($_SESSION['message']); } ?>

<!-- Select Teacher -->
<div class="form-box">
  <form method="get">
    <div class="form-group">
      <label for="teacher_id">Select Teacher</label>
      <select name="teacher_id" id="teacher_id" onchange="this.form.submit()" required>
        <option value="">-- Choose Teacher --</option>
        <?php while ($row = $teachers->fetch_assoc()): ?>
          <option value="<?= $row['id'] ?>" <?= (isset($_GET['teacher_id']) && $_GET['teacher_id']==$row['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($row['code'].' - '.$row['name']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
  </form>
</div>

<?php if ($selected_teacher): ?>
<!-- Teacher Info -->
<div class="form-box">
  <h3>Teacher Details</h3>
  <p><b>Name:</b> <?= htmlspecialchars($selected_teacher['name']) ?></p>
  <p><b>Code:</b> <?= htmlspecialchars($selected_teacher['code']) ?></p>
  <p><b>Phone:</b> <?= htmlspecialchars($selected_teacher['phone']) ?></p>
  <p><b>Email:</b> <?= htmlspecialchars($selected_teacher['email']) ?></p>
</div>

<!-- Payment Form -->
<div class="form-box">
  <h3>Record Payment</h3>
  <form method="POST">
    <input type="hidden" name="teacher_id" value="<?= $selected_teacher['id'] ?>">
    <div class="form-group"><label>Base Amount (Salary)</label><input type="number" step="0.01" name="amount" required></div>
    <div class="form-group"><label>Allowance</label><input type="number" step="0.01" name="allowance"></div>
    <div class="form-group"><label>Deduction</label><input type="number" step="0.01" name="deduction"></div>
    <div class="form-group"><label>Payment Date</label><input type="date" name="pay_date" required></div>
    <div class="form-group"><label>Remarks</label><textarea name="remarks" rows="3"></textarea></div>
    <button type="submit" name="pay_teacher" class="save-btn"><i class="bx bx-save"></i> Save Payment</button>
  </form>
</div>

<!-- Past Payments -->
<div class="table-container">
  <h3>Payment History</h3>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Amount</th>
        <th>Allowance</th>
        <th>Deduction</th>
        <th>Net Pay</th>
        <th>Date</th>
        <th>Remarks</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($payments && $payments->num_rows > 0): $i=1; ?>
        <?php while ($pay = $payments->fetch_assoc()): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= number_format($pay['amount'],2) ?></td>
            <td><?= number_format($pay['allowance'],2) ?></td>
            <td><?= number_format($pay['deduction'],2) ?></td>
            <td><b><?= number_format($pay['net_pay'],2) ?></b></td>
            <td><?= htmlspecialchars($pay['pay_date']) ?></td>
            <td><?= htmlspecialchars($pay['remarks']) ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="7" style="text-align:center;color:#888">No payments recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

</main>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
