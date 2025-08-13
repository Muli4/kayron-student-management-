<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

include '../php/db.php';

// Handle update POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $total_amount = floatval($_POST['total_amount']);
    $total_paid = floatval($_POST['total_paid']);
    $balance = floatval($_POST['balance']);
    $monday = floatval($_POST['monday']);
    $tuesday = floatval($_POST['tuesday']);
    $wednesday = floatval($_POST['wednesday']);
    $thursday = floatval($_POST['thursday']);
    $friday = floatval($_POST['friday']);
    $carry_forward = floatval($_POST['carry_forward']);

    $update_sql = "UPDATE lunch_fees SET
        total_amount = ?, total_paid = ?, balance = ?,
        monday = ?, tuesday = ?, wednesday = ?, thursday = ?, friday = ?,
        carry_forward = ?
        WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("dddddddddi",
        $total_amount, $total_paid, $balance,
        $monday, $tuesday, $wednesday, $thursday, $friday,
        $carry_forward, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
    exit();
}

$admission_no_filter = $_GET['admission_no'] ?? '';
$term_number_filter = $_GET['term_number'] ?? '';

// Build WHERE clause dynamically
$whereClauses = [];
$params = [];
$types = '';

if ($admission_no_filter !== '') {
    $whereClauses[] = 'lf.admission_no = ?';
    $params[] = $admission_no_filter;
    $types .= 's';
}

if ($term_number_filter !== '') {
    $whereClauses[] = 't.term_number = ?';
    $params[] = $term_number_filter;
    $types .= 'i';
}

$whereSQL = '';
if (count($whereClauses) > 0) {
    $whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);
}

$sql = "SELECT lf.*, t.term_number, t.year 
        FROM lunch_fees lf
        LEFT JOIN terms t ON lf.term_id = t.id
        $whereSQL
        ORDER BY lf.id ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}

if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Lunch Fees Admin - Filter & Edit</title>
<style>
/* Simple styles */
body { font-family: Arial, sans-serif; padding: 20px; background: #f0f0f0; }
.container { max-width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: 6px; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
th { background: #2980b9; color: #fff; }
tr:nth-child(even) { background: #f9f9f9; }
.edit-btn { padding: 5px 10px; cursor: pointer; background: #27ae60; color: white; border: none; border-radius: 3px; }
#editModal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    justify-content: center;
    align-items: center;
    z-index: 10000;
}
#editModal .modal-content {
    background: white;
    padding: 20px;
    border-radius: 6px;
    max-width: 500px;
    width: 100%;
    max-height: 80vh;         /* limit modal height to 80% of viewport height */
    overflow-y: auto;         /* enable vertical scrollbar if content is taller */
    box-sizing: border-box;
}

#editModal label { display: block; margin-top: 10px; }
#editModal input { width: 100%; padding: 6px; margin-top: 4px; box-sizing: border-box; }
#editModal button { margin-top: 15px; padding: 8px 15px; }
</style>
</head>
<body>

<div class="container">
    <h2>Lunch Fees Records - Admin Filter & Edit</h2>

    <form method="GET" style="margin-bottom: 20px;">
        <label>
            Admission No:
            <input type="text" name="admission_no" value="<?= htmlspecialchars($admission_no_filter) ?>">
        </label>
        &nbsp;&nbsp;
        <label>
            Term Number:
            <input type="number" name="term_number" value="<?= htmlspecialchars($term_number_filter) ?>">
        </label>
        &nbsp;&nbsp;
        <button type="submit">Filter</button>
        <button type="button" onclick="window.location='<?= $_SERVER['PHP_SELF'] ?>'">Reset</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Admission No</th>
                <th>Term Number</th>
                <th>Year</th>
                <th>Total Amount</th>
                <th>Total Paid</th>
                <th>Balance</th>
                <th>Monday</th>
                <th>Tuesday</th>
                <th>Wednesday</th>
                <th>Thursday</th>
                <th>Friday</th>
                <th>Carry Forward</th>
                <th>Edit</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['admission_no']) ?></td>
                    <td><?= htmlspecialchars($row['term_number']) ?></td>
                    <td><?= htmlspecialchars($row['year']) ?></td>
                    <td><?= number_format($row['total_amount'], 2) ?></td>
                    <td><?= number_format($row['total_paid'], 2) ?></td>
                    <td><?= number_format($row['balance'], 2) ?></td>
                    <td><?= number_format($row['monday'], 2) ?></td>
                    <td><?= number_format($row['tuesday'], 2) ?></td>
                    <td><?= number_format($row['wednesday'], 2) ?></td>
                    <td><?= number_format($row['thursday'], 2) ?></td>
                    <td><?= number_format($row['friday'], 2) ?></td>
                    <td><?= number_format($row['carry_forward'], 2) ?></td>
                    <td>
                        <button 
                            class="edit-btn" 
                            data-id="<?= $row['id'] ?>"
                            data-total_amount="<?= $row['total_amount'] ?>"
                            data-total_paid="<?= $row['total_paid'] ?>"
                            data-balance="<?= $row['balance'] ?>"
                            data-monday="<?= $row['monday'] ?>"
                            data-tuesday="<?= $row['tuesday'] ?>"
                            data-wednesday="<?= $row['wednesday'] ?>"
                            data-thursday="<?= $row['thursday'] ?>"
                            data-friday="<?= $row['friday'] ?>"
                            data-carry_forward="<?= $row['carry_forward'] ?>"
                        >Edit</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="14" style="text-align:center;">No records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<a href="../php/master_panel.php" class="back-link">← Back to Master Panel</a>

<!-- Edit Modal -->
<div id="editModal">
    <div class="modal-content">
        <h3>Edit Lunch Fee Record</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="id" id="edit_id">

            <label>Total Amount:</label>
            <input type="number" step="0.01" name="total_amount" id="edit_total_amount" required>

            <label>Total Paid:</label>
            <input type="number" step="0.01" name="total_paid" id="edit_total_paid" required>

            <label>Balance:</label>
            <input type="number" step="0.01" name="balance" id="edit_balance" required>

            <label>Monday:</label>
            <input type="number" step="0.01" name="monday" id="edit_monday" required>

            <label>Tuesday:</label>
            <input type="number" step="0.01" name="tuesday" id="edit_tuesday" required>

            <label>Wednesday:</label>
            <input type="number" step="0.01" name="wednesday" id="edit_wednesday" required>

            <label>Thursday:</label>
            <input type="number" step="0.01" name="thursday" id="edit_thursday" required>

            <label>Friday:</label>
            <input type="number" step="0.01" name="friday" id="edit_friday" required>

            <label>Carry Forward:</label>
            <input type="number" step="0.01" name="carry_forward" id="edit_carry_forward" required>

            <button type="submit">Update</button>
            <button type="button" id="closeModalBtn">Cancel</button>
        </form>
    </div>
</div>

<script>
const editModal = document.getElementById('editModal');
const editForm = document.getElementById('editForm');
const closeModalBtn = document.getElementById('closeModalBtn');

document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit_id').value = btn.dataset.id;
        document.getElementById('edit_total_amount').value = btn.dataset.total_amount;
        document.getElementById('edit_total_paid').value = btn.dataset.total_paid;
        document.getElementById('edit_balance').value = btn.dataset.balance;
        document.getElementById('edit_monday').value = btn.dataset.monday;
        document.getElementById('edit_tuesday').value = btn.dataset.tuesday;
        document.getElementById('edit_wednesday').value = btn.dataset.wednesday;
        document.getElementById('edit_thursday').value = btn.dataset.thursday;
        document.getElementById('edit_friday').value = btn.dataset.friday;
        document.getElementById('edit_carry_forward').value = btn.dataset.carry_forward;

        editModal.style.display = 'flex';
    });
});

closeModalBtn.addEventListener('click', () => {
    editModal.style.display = 'none';
});

// Close modal when clicking outside the modal content
window.addEventListener('click', e => {
    if (e.target === editModal) {
        editModal.style.display = 'none';
    }
});
</script>

</body>
</html>
