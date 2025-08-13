<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

include '../php/db.php';

$classes = ['babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6'];
$filter_class = $_GET['class'] ?? '';

// Handle form submission for update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admission_no'])) {
    $admission_no = $_POST['admission_no'];
    $total_fee = floatval($_POST['total_fee'] ?? 0);
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $balance = floatval($_POST['balance'] ?? 0);

    $update_sql = "UPDATE school_fees SET total_fee = ?, amount_paid = ?, balance = ? WHERE admission_no = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ddds", $total_fee, $amount_paid, $balance, $admission_no);
    $stmt->execute();

    // Redirect to avoid form resubmission on refresh
    header("Location: " . $_SERVER['PHP_SELF'] . ($filter_class ? "?class=" . urlencode($filter_class) : ""));
    exit();
}

// Prepare select query
$whereClause = '';
$params = [];
if ($filter_class) {
    $whereClause = 'WHERE class = ?';
    $params[] = $filter_class;
}
$sql = "SELECT admission_no, birth_cert, class, term, total_fee, amount_paid, balance, created_at
        FROM school_fees $whereClause
        ORDER BY admission_no ASC";
$stmt = $conn->prepare($sql);
if ($filter_class) {
    $stmt->bind_param("s", $filter_class);
}
$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>View and Edit School Fees</title>
<style>
    body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
    .container { max-width: 1000px; margin: auto; background: #fff; padding: 20px; border-radius: 6px; }
    h2 { text-align: center; margin-bottom: 20px; }
    .filter-form { margin-bottom: 20px; text-align: center; }
    select { padding: 6px; font-size: 16px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 8px; border: 1px solid #ccc; text-align: center; }
    th { background: #2980b9; color: white; }
    tr:nth-child(even) { background: #f9f9f9; }
    button.edit-btn { padding: 5px 10px; cursor: pointer; }
    /* Modal styles */
    #editModal {
        display: none;
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        justify-content: center; align-items: center;
        z-index: 9999;
    }
    #editModal .modal-content {
        background: white; padding: 20px; border-radius: 6px; width: 400px; position: relative;
    }
    #editModal label {
        display: block; margin-bottom: 8px;
    }
    #editModal input {
        width: 100%; padding: 6px; margin-top: 4px; margin-bottom: 12px; box-sizing: border-box;
    }
    #editModal button {
        padding: 8px 15px; margin-right: 10px;
    }
</style>
</head>
<body>
<div class="container">
    <h2>School Fees Records</h2>

    <form method="GET" class="filter-form">
        <label>
            Filter by Class:
            <select name="class" onchange="this.form.submit()">
                <option value="">-- All Classes --</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c ?>" <?= $filter_class === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <table>
        <thead>
            <tr>
                <th>Admission No</th>
                <th>Birth Certificate</th>
                <th>Class</th>
                <th>Term</th>
                <th>Total Fee</th>
                <th>Amount Paid</th>
                <th>Balance</th>
                <th>Created At</th>
                <th>Edit</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['admission_no']) ?></td>
                    <td><?= htmlspecialchars($row['birth_cert']) ?></td>
                    <td><?= htmlspecialchars($row['class']) ?></td>
                    <td><?= htmlspecialchars($row['term']) ?></td>
                    <td><?= number_format($row['total_fee'], 2) ?></td>
                    <td><?= number_format($row['amount_paid'], 2) ?></td>
                    <td><?= number_format($row['balance'], 2) ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td>
                        <button class="edit-btn"
                            data-admission="<?= htmlspecialchars($row['admission_no']) ?>"
                            data-total_fee="<?= htmlspecialchars($row['total_fee']) ?>"
                            data-amount_paid="<?= htmlspecialchars($row['amount_paid']) ?>"
                            data-balance="<?= htmlspecialchars($row['balance']) ?>"
                        >Edit</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="9" style="text-align:center;">No records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<a href="../php/master_panel.php" class="back-link">← Back to Master Panel</a>

<!-- Edit Modal -->
<div id="editModal">
    <div class="modal-content">
        <h3>Edit School Fee</h3>
        <form id="editForm" method="POST" action="">
            <label for="admission_no">Admission No:</label>
            <input type="text" name="admission_no" id="admission_no" readonly>

            <label for="total_fee">Total Fee:</label>
            <input type="number" step="0.01" name="total_fee" id="total_fee" required>

            <label for="amount_paid">Amount Paid:</label>
            <input type="number" step="0.01" name="amount_paid" id="amount_paid" required>

            <label for="balance">Balance:</label>
            <input type="number" step="0.01" name="balance" id="balance" required>

            <button type="submit">Update</button>
            <button type="button" id="closeModal">Cancel</button>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.edit-btn').forEach(button => {
    button.addEventListener('click', () => {
        document.getElementById('admission_no').value = button.dataset.admission;
        document.getElementById('total_fee').value = button.dataset.total_fee;
        document.getElementById('amount_paid').value = button.dataset.amount_paid;
        document.getElementById('balance').value = button.dataset.balance;

        document.getElementById('editModal').style.display = 'flex';
    });
});

document.getElementById('closeModal').addEventListener('click', () => {
    document.getElementById('editModal').style.display = 'none';
});
</script>

</body>
</html>
