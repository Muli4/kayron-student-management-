<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
include 'db.php';

$daily_fee = 70;
$predefined_classes = ['babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6'];

// === 1. Get the latest registered week number ===
$stmt_max_week = $conn->prepare("
    SELECT MAX(w.week_number) AS max_week
    FROM weeks w
    JOIN days d ON d.week_id = w.id
");
$stmt_max_week->execute();
$result = $stmt_max_week->get_result();
$max_week = ($row = $result->fetch_assoc()) ? (int)$row['max_week'] : 0;
$stmt_max_week->close();

$week_number = $max_week;

$balance_filter = isset($_GET['balance_filter']) && $_GET['balance_filter'] !== ''
    ? (float)$_GET['balance_filter']
    : 0;

$class_filter = isset($_GET['class_filter']) ? $_GET['class_filter'] : '';

// === 2. Fetch students ===
$query = "SELECT admission_no, name, class FROM student_records";
if ($class_filter !== '' && in_array($class_filter, $predefined_classes)) {
    $query .= " WHERE class = ?";
    $stmt_students = $conn->prepare($query);
    $stmt_students->bind_param("s", $class_filter);
} else {
    $stmt_students = $conn->prepare($query);
}

$stmt_students->execute();
$result_students = $stmt_students->get_result();

$students = [];

while ($student = $result_students->fetch_assoc()) {
    $admission_no = $student['admission_no'];

    // === School fee balance ===
    $stmt_school = $conn->prepare("SELECT balance FROM school_fees WHERE admission_no = ?");
    $stmt_school->bind_param("s", $admission_no);
    $stmt_school->execute();
    $res_school = $stmt_school->get_result();
    $school_fees_balance = ($row = $res_school->fetch_assoc()) ? (float)$row['balance'] : 0;
    $stmt_school->close();

    // === Book balance ===
    $stmt_books = $conn->prepare("SELECT SUM(balance) AS total_balance FROM book_purchases WHERE admission_no = ?");
    $stmt_books->bind_param("s", $admission_no);
    $stmt_books->execute();
    $res_books = $stmt_books->get_result();
    $book_balance = ($row = $res_books->fetch_assoc()) ? (float)$row['total_balance'] : 0;
    $stmt_books->close();

    // === Uniform balance ===
    $stmt_uniform = $conn->prepare("SELECT SUM(balance) AS total_balance FROM uniform_purchases WHERE admission_no = ?");
    $stmt_uniform->bind_param("s", $admission_no);
    $stmt_uniform->execute();
    $res_uniform = $stmt_uniform->get_result();
    $uniform_balance = ($row = $res_uniform->fetch_assoc()) ? (float)$row['total_balance'] : 0;
    $stmt_uniform->close();

    // === Lunch calculation ===
    $stmt_days = $conn->prepare("
        SELECT d.id AS day_id, d.is_public_holiday
        FROM days d
        JOIN weeks w ON d.week_id = w.id
        WHERE w.week_number <= ?
        ORDER BY w.week_number ASC,
                 FIELD(d.day_name,'Monday','Tuesday','Wednesday','Thursday','Friday') ASC
    ");
    $stmt_days->bind_param("i", $week_number);
    $stmt_days->execute();
    $result_days = $stmt_days->get_result();

    $expected_lunch = 0;
    $has_week_records = false;

    while ($day_row = $result_days->fetch_assoc()) {
        $has_week_records = true;
        $day_id = $day_row['day_id'];
        $is_holiday = (int)$day_row['is_public_holiday'];

        if ($is_holiday) continue;

        $att_stmt = $conn->prepare("SELECT status FROM attendance WHERE admission_no=? AND day_id=?");
        $att_stmt->bind_param("si", $admission_no, $day_id);
        $att_stmt->execute();
        $att_stmt->bind_result($status);
        $att_stmt->fetch();
        $att_stmt->close();

        if ($status === 'Absent') continue;

        $expected_lunch += $daily_fee;
    }
    $stmt_days->close();

    if (!$has_week_records) {
        $students[] = [
            'admission_no'       => $admission_no,
            'name'               => $student['name'],
            'school_fees'        => $school_fees_balance,
            'lunch_fee'          => "No week records",
            'book_purchases'     => $book_balance,
            'uniform_purchases'  => $uniform_balance,
            'total_balance'      => $school_fees_balance + $book_balance + $uniform_balance,
            'class'              => $student['class']
        ];
        continue;
    }

    // === Lunch paid ===
    $stmt_paid = $conn->prepare("
        SELECT 
            SUM(COALESCE(monday,0) + COALESCE(tuesday,0) + COALESCE(wednesday,0) +
                COALESCE(thursday,0) + COALESCE(friday,0)) AS total_paid
        FROM lunch_fees
        WHERE admission_no = ? AND week_number <= ?
    ");
    $stmt_paid->bind_param("si", $admission_no, $week_number);
    $stmt_paid->execute();
    $res_paid = $stmt_paid->get_result();
    $paid_row = $res_paid->fetch_assoc();
    $stmt_paid->close();

    $total_paid = (float)($paid_row['total_paid'] ?? 0);
    $lunch_balance = $expected_lunch - $total_paid;
    $lunch_display = $lunch_balance <= 0 ? "Paid" : $lunch_balance;

    $school_fees_display = $school_fees_balance <= 0 ? "Paid" : $school_fees_balance;

    $total_balance = max(0, $school_fees_balance) + $book_balance + $uniform_balance + max(0, $lunch_balance);
    $total_display = $total_balance <= 0 ? "Paid" : $total_balance;

    if ($total_balance >= $balance_filter) {
        $students[] = [
            'admission_no'       => $admission_no,
            'name'               => $student['name'],
            'school_fees'        => $school_fees_display,
            'lunch_fee'          => $lunch_display,
            'book_purchases'     => $book_balance,
            'uniform_purchases'  => $uniform_balance,
            'total_balance'      => $total_display,
            'class'              => $student['class']
        ];
    }
}
$stmt_students->close();
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Balances</title>
    <link rel="stylesheet" href="../style/style.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
    <link rel="website icon" type="png" href="photos/Logo.jpg">

    <style>
        select {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background-color: #f8f8f8;
            font-size: 16px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            cursor: pointer;
            transition: border 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        select:focus {
            border-color: #007BFF;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
            outline: none;
        }
    </style>

    <script>
        let typingTimer;
        const debounceDelay = 1500;

        function autoSubmit() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, debounceDelay);
        }

        function printBalances() {
            const printContent = document.getElementById('balancesTable').outerHTML;
            const newWin = window.open("", "_blank");
            newWin.document.write("<html><head><title>Print Balances</title></head><body>");
            newWin.document.write(printContent);
            newWin.document.write("</body></html>");
            newWin.document.close();
            newWin.print();
        }
    </script>
</head>

<body>
<?php include '../includes/header.php'; ?>
<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    <main class="content">
        <div class="view-container">
            <h2>Student Balances</h2>

            <form method="GET" action="" id="filterForm" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <label for="balance_filter">Balance</label>
                <input type="number" id="balance_filter" name="balance_filter" min="0" step="0.01" value="<?= htmlspecialchars($balance_filter); ?>" oninput="autoSubmit()">

                <label for="class_filter">Class:</label>
                <select name="class_filter" id="class_filter" onchange="autoSubmit()">
                    <option value="">-- All Classes --</option>
                    <?php foreach ($predefined_classes as $class): ?>
                        <option value="<?= htmlspecialchars($class); ?>" <?= ($class_filter === $class) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($class)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="button" onclick="printBalances()"><i class='bx bx-printer' style="font-size: 20px;"></i></button>
            </form>

            <table id="balancesTable" border="1">
                <thead>
                <tr>
                    <th>Adm No</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>School Fees</th>
                    <th>Lunch (W<?= htmlspecialchars($week_number); ?>)</th>
                    <th>Books</th>
                    <th>Uniform</th>
                    <th>Total Balance</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="8">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= htmlspecialchars($student['admission_no']); ?></td>
                            <td><?= htmlspecialchars($student['name']); ?></td>
                            <td><?= htmlspecialchars($student['class']); ?></td>
                            <td><?= htmlspecialchars($student['school_fees']); ?></td>
                            <td><?= htmlspecialchars($student['lunch_fee']); ?></td>
                            <td><?= htmlspecialchars($student['book_purchases']); ?></td>
                            <td><?= htmlspecialchars($student['uniform_purchases']); ?></td>
                            <td><?= htmlspecialchars($student['total_balance']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
