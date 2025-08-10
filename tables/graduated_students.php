<?php
session_start();
require '../php/db.php';

// Handle unpromote action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unpromote_admission_no'])) {
    $admission_no = $conn->real_escape_string($_POST['unpromote_admission_no']);
    
    $res = $conn->query("SELECT * FROM graduated_students WHERE admission_no='$admission_no'");
    if ($res && $res->num_rows === 1) {
        $grad = $res->fetch_assoc();

        $photo = isset($grad['student_photo']) ? $conn->real_escape_string($grad['student_photo']) : '';

        $sql_insert = "INSERT INTO student_records
            (admission_no, name, birth_cert, dob, gender, class, term, religion, guardian, phone, student_photo)
            VALUES
            (
                '{$conn->real_escape_string($grad['admission_no'])}',
                '{$conn->real_escape_string($grad['name'])}',
                '{$conn->real_escape_string($grad['birth_certificate'])}',
                '{$conn->real_escape_string($grad['dob'])}',
                '{$conn->real_escape_string($grad['gender'])}',
                '{$conn->real_escape_string($grad['class_completed'])}',
                '{$conn->real_escape_string($grad['term'])}',
                '{$conn->real_escape_string($grad['religion'])}',
                '{$conn->real_escape_string($grad['guardian_name'])}',
                '{$conn->real_escape_string($grad['phone'])}',
                '$photo'
            )";

        if ($conn->query($sql_insert) === TRUE) {
            $conn->query("DELETE FROM graduated_students WHERE admission_no='$admission_no'");
            $_SESSION['msg'] = "Student has been unpromoted successfully.";
        } else {
            $_SESSION['msg'] = "An error occurred while unpromoting.";
        }
    } else {
        $_SESSION['msg'] = "Graduate record not found.";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Get message from session (if any), then clear it
$msg = '';
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// Fetch graduates
$result = $conn->query("SELECT * FROM graduated_students ORDER BY graduation_date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Graduated Students</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9fbfc;
            margin: 20px;
            color: #333;
        }

        h1 {
            color: #2c3e50;
            text-align: center;
        }

        .btn {
            padding: 6px 12px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
        }

        .btn:hover {
            background: #2c80b4;
        }

        .btn-unpromote {
            background: #e67e22;
        }

        .btn-unpromote:hover {
            background: #cf711c;
        }

        .msg {
            background: #dff0d8;
            color: #3c763d;
            padding: 10px;
            border-radius: 5px;
            max-width: 600px;
            margin: 0 auto 15px;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            font-size: 0.95em;
        }

        th {
            background: #2c3e50;
            color: white;
        }

        tr:nth-child(even) {
            background: #f4f6f8;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: #2980b9;
            font-weight: bold;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            table, th, td {
                font-size: 0.85em;
            }
        }
    </style>
</head>
<body>

<h1>Graduated Students</h1>

<?php if (!empty($msg)): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>Admission No</th>
            <th>Name</th>
            <th>Class Completed</th>
            <th>Term</th>
            <th>Guardian</th>
            <th>Phone</th>
            <th>Graduation Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($g = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($g['admission_no']) ?></td>
                <td><?= htmlspecialchars($g['name']) ?></td>
                <td><?= htmlspecialchars($g['class_completed']) ?></td>
                <td><?= htmlspecialchars($g['term']) ?></td>
                <td><?= htmlspecialchars($g['guardian_name']) ?></td>
                <td><?= htmlspecialchars($g['phone']) ?></td>
                <td><?= htmlspecialchars($g['graduation_date']) ?></td>
                <td>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Unpromote this student?');">
                        <input type="hidden" name="unpromote_admission_no" value="<?= htmlspecialchars($g['admission_no']) ?>">
                        <button type="submit" class="btn btn-unpromote">Unpromote</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<a href="../php/master_panel.php" class="back-link">← Back to Master Panel</a>

</body>
</html>
