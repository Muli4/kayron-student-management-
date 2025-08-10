<?php
session_start();
require '../php/db.php';

$msg = '';

// Handle form submission for update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admission_no'])) {
    $admission_no = $conn->real_escape_string($_POST['admission_no']);
    $birth_cert = $conn->real_escape_string(trim($_POST['birth_cert']));
    $class = in_array($_POST['class'], ['babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6']) ? $_POST['class'] : 'babyclass';
    $term = in_array($_POST['term'], ['term1','term2','term3']) ? $_POST['term'] : 'term1';
    $total_fee = floatval($_POST['total_fee']);
    $amount_paid = floatval($_POST['amount_paid']);
    $balance = $total_fee - $amount_paid;

    $sql = "UPDATE school_fees SET 
        birth_cert='$birth_cert',
        class='$class',
        term='$term',
        total_fee=$total_fee,
        amount_paid=$amount_paid,
        balance=$balance
        WHERE admission_no='$admission_no'";

    if ($conn->query($sql)) {
        $_SESSION['msg'] = "Record updated successfully.";
    } else {
        $_SESSION['msg'] = "Error updating record: " . $conn->error;
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Show flash message
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// Fetch all school fees records
$result = $conn->query("SELECT * FROM school_fees ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>School Fees Management</title>
    <style>
        /* General styling */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7f9;
            margin: 40px;
            color: #333;
        }
        h1 {
            color: #2a3f54;
            text-align: center;
            margin-bottom: 30px;
            font-weight: 700;
        }

        /* Flash message */
        .msg {
            background-color: #dff0d8;
            border: 1px solid #a6d48a;
            padding: 15px 20px;
            border-radius: 5px;
            max-width: 900px;
            margin: 0 auto 20px auto;
            color: #3c763d;
            font-weight: 600;
        }
        .error {
            background-color: #f2dede;
            border-color: #ebccd1;
            color: #a94442;
        }

        /* Table styling */
        table {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        thead {
            background-color: #2a3f54;
            color: white;
            font-weight: 700;
        }
        th, td {
            padding: 14px 18px;
            text-align: left;
            border-bottom: 1px solid #e0e4e8;
            vertical-align: middle;
        }
        tbody tr:hover {
            background-color: #f5f9fc;
        }
        tbody tr:nth-child(even) {
            background-color: #f9fbfc;
        }

        /* Form elements */
        input[type="text"], input[type="number"], select {
            width: 100%;
            padding: 8px 10px;
            margin: 0;
            box-sizing: border-box;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }
        input[type="text"]:focus, input[type="number"]:focus, select:focus {
            border-color: #3182ce;
            outline: none;
            background: #e6f0fa;
        }

        /* Buttons */
        button, a.button-link {
            display: inline-block;
            padding: 8px 14px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s ease;
            user-select: none;
        }
        button {
            background-color: #3182ce;
            color: white;
            border: none;
        }
        button:hover {
            background-color: #2c6eb2;
        }
        a.button-link {
            background-color: #e2e8f0;
            color: #2a3f54;
            border: 1px solid #cbd5e0;
            margin-left: 5px;
        }
        a.button-link:hover {
            background-color: #cbd5e0;
        }

        /* Responsive */
        @media (max-width: 720px) {
            body {
                margin: 20px 10px;
            }
            table, thead, tbody, th, td, tr {
                display: block;
                width: 100%;
            }
            thead tr {
                display: none;
            }
            tbody tr {
                margin-bottom: 15px;
                border-radius: 8px;
                background: white;
                box-shadow: 0 3px 10px rgba(0,0,0,0.05);
                padding: 15px;
            }
            tbody td {
                border: none;
                padding: 8px 0;
                position: relative;
                padding-left: 50%;
                text-align: right;
            }
            tbody td::before {
                content: attr(data-label);
                position: absolute;
                left: 15px;
                width: 45%;
                padding-left: 15px;
                font-weight: 700;
                text-align: left;
                color: #555;
            }
            button, a.button-link {
                width: 48%;
                margin: 5px 1% 0 1%;
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body>
    <h1>School Fees Records</h1>

    <?php if ($msg): ?>
        <div class="msg"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Admission No</th>
                <th>Birth Cert</th>
                <th>Class</th>
                <th>Term</th>
                <th>Total Fee</th>
                <th>Amount Paid</th>
                <th>Balance</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <?php if (isset($_GET['edit']) && $_GET['edit'] === $row['admission_no']): ?>
                <!-- Edit form -->
                <tr>
                    <form method="post" action="">
                        <td data-label="Admission No"><?= htmlspecialchars($row['admission_no']) ?>
                            <input type="hidden" name="admission_no" value="<?= htmlspecialchars($row['admission_no']) ?>">
                        </td>
                        <td data-label="Birth Cert"><input type="text" name="birth_cert" value="<?= htmlspecialchars($row['birth_cert']) ?>" required></td>
                        <td data-label="Class">
                            <select name="class" required>
                                <?php 
                                $classes = ['babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6'];
                                foreach ($classes as $c): ?>
                                    <option value="<?= $c ?>" <?= $row['class'] === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td data-label="Term">
                            <select name="term" required>
                                <?php 
                                $terms = ['term1','term2','term3'];
                                foreach ($terms as $t): ?>
                                    <option value="<?= $t ?>" <?= $row['term'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td data-label="Total Fee"><input type="number" step="0.01" name="total_fee" value="<?= htmlspecialchars($row['total_fee']) ?>" required></td>
                        <td data-label="Amount Paid"><input type="number" step="0.01" name="amount_paid" value="<?= htmlspecialchars($row['amount_paid']) ?>" required></td>
                        <td data-label="Balance"><?= number_format($row['balance'], 2) ?></td>
                        <td data-label="Created At"><?= htmlspecialchars($row['created_at']) ?></td>
                        <td data-label="Actions">
                            <button type="submit">Save</button>
                            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="button-link">Cancel</a>
                        </td>
                    </form>
                </tr>
            <?php else: ?>
                <!-- Display row -->
                <tr>
                    <td data-label="Admission No"><?= htmlspecialchars($row['admission_no']) ?></td>
                    <td data-label="Birth Cert"><?= htmlspecialchars($row['birth_cert']) ?></td>
                    <td data-label="Class"><?= ucfirst(htmlspecialchars($row['class'])) ?></td>
                    <td data-label="Term"><?= ucfirst(htmlspecialchars($row['term'])) ?></td>
                    <td data-label="Total Fee"><?= number_format($row['total_fee'], 2) ?></td>
                    <td data-label="Amount Paid"><?= number_format($row['amount_paid'], 2) ?></td>
                    <td data-label="Balance"><?= number_format($row['balance'], 2) ?></td>
                    <td data-label="Created At"><?= htmlspecialchars($row['created_at']) ?></td>
                    <td data-label="Actions"><a href="?edit=<?= urlencode($row['admission_no']) ?>" class="button-link" style="background:#3182ce;color:#fff;border:none;">Edit</a></td>
                </tr>
            <?php endif; ?>
        <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
