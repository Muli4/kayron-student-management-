<?php
session_start();
require '../php/db.php';

// Handle AJAX request for student data (replaces get_student.php)
if (isset($_GET['admission_no']) && !isset($_GET['promote']) && !isset($_GET['delete'])) {
    header('Content-Type: application/json');
    $admission_no = $conn->real_escape_string($_GET['admission_no']);
    $result = $conn->query("SELECT * FROM student_records WHERE admission_no='$admission_no'");

    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Student not found']);
    } else {
        echo json_encode($result->fetch_assoc());
    }
    exit;
}

$msg = '';

// Handle form submission (add/edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admission_no = $conn->real_escape_string(trim($_POST['admission_no']));
    $birth_cert = $conn->real_escape_string(trim($_POST['birth_cert']));
    $name = $conn->real_escape_string(trim($_POST['name']));
    $dob = $_POST['dob'] ?: null;
    $gender = in_array($_POST['gender'], ['male', 'female']) ? $_POST['gender'] : 'male';
    $class = in_array($_POST['class'], ['babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6']) ? $_POST['class'] : 'babyclass';
    $term = in_array($_POST['term'], ['term1','term2','term3']) ? $_POST['term'] : 'term1';
    $religion = in_array($_POST['religion'], ['christian','muslim','other']) ? $_POST['religion'] : null;
    $guardian = $conn->real_escape_string(trim($_POST['guardian']));
    $phone = intval($_POST['phone']);

    // Handle photo upload (optional)
    $photoData = null;
    if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === UPLOAD_ERR_OK) {
        $photoData = file_get_contents($_FILES['student_photo']['tmp_name']);
        $photoData = $conn->real_escape_string($photoData);
    }

    // Check if admission_no exists => update else insert
    $check = $conn->query("SELECT admission_no FROM student_records WHERE admission_no='$admission_no'");
    if ($check->num_rows > 0) {
        // UPDATE
        $sql = "UPDATE student_records SET
                birth_cert='$birth_cert',
                name='$name',
                dob=" . ($dob ? "'$dob'" : "NULL") . ",
                gender='$gender',
                class='$class',
                term='$term',
                religion=" . ($religion ? "'$religion'" : "NULL") . ",
                guardian='$guardian',
                phone=$phone";

        if ($photoData !== null) {
            $sql .= ", student_photo='$photoData'";
        }
        $sql .= " WHERE admission_no='$admission_no'";
        $_SESSION['msg'] = $conn->query($sql) ? "Student updated successfully." : "Error updating student: " . $conn->error;
    } else {
        // INSERT
        $photoSql = $photoData !== null ? "'$photoData'" : "NULL";
        $dobSql = $dob ? "'$dob'" : "NULL";
        $religionSql = $religion ? "'$religion'" : "NULL";

        $sql = "INSERT INTO student_records(admission_no, birth_cert, name, dob, gender, student_photo, class, term, religion, guardian, phone)
                VALUES ('$admission_no', '$birth_cert', '$name', $dobSql, '$gender', $photoSql, '$class', '$term', $religionSql, '$guardian', $phone)";
        $_SESSION['msg'] = $conn->query($sql) ? "Student added successfully." : "Error adding student: " . $conn->error;
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $del = $conn->real_escape_string($_GET['delete']);
    $conn->query("DELETE FROM student_records WHERE admission_no='$del'");
    $_SESSION['msg'] = "Student deleted successfully.";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle promote
if (isset($_GET['promote'])) {
    $promote_no = $conn->real_escape_string($_GET['promote']);

    $studentResult = $conn->query("SELECT * FROM student_records WHERE admission_no='$promote_no'");
    if ($studentResult->num_rows > 0) {
        $student = $studentResult->fetch_assoc();

        $admission_no = $conn->real_escape_string($student['admission_no']);
        $name = $conn->real_escape_string($student['name']);
        $birth_certificate = $conn->real_escape_string($student['birth_cert']);
        $dob = $student['dob'] ? "'".$conn->real_escape_string($student['dob'])."'" : "NULL";
        $gender = $conn->real_escape_string($student['gender']);
        $class_completed = $conn->real_escape_string($student['class']);
        $term = $conn->real_escape_string($student['term']);
        $religion = $student['religion'] ? "'".$conn->real_escape_string($student['religion'])."'" : "NULL";
        $guardian_name = $conn->real_escape_string($student['guardian']);
        $phone = intval($student['phone']);
        $student_photo = $student['student_photo'] ? "'".$conn->real_escape_string($student['student_photo'])."'" : "NULL";
        $year_completed = date('Y');
        $graduation_date = "'".date('Y-m-d')."'";

        $insertSql = "INSERT INTO graduated_students (
            admission_no, name, birth_certificate, dob, gender, class_completed, term, religion, guardian_name, phone, student_photo, year_completed, graduation_date
        ) VALUES (
            '$admission_no', '$name', '$birth_certificate', $dob, '$gender', '$class_completed', '$term', $religion, '$guardian_name', $phone, $student_photo, $year_completed, $graduation_date
        )";

        if ($conn->query($insertSql)) {
            $conn->query("DELETE FROM student_records WHERE admission_no='$admission_no'");
            $_SESSION['msg'] = "Student promoted successfully.";
        } else {
            $_SESSION['msg'] = "Error promoting student: " . $conn->error;
        }
    } else {
        $_SESSION['msg'] = "Student not found.";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Show flash message
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// Fetch all students
$result = $conn->query("SELECT admission_no, birth_cert, name, dob, gender, class, term, religion, guardian, phone, created_at, student_photo FROM student_records ORDER BY created_at DESC");
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Student Records</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7fa;
            padding: 20px;
            color: #333;
        }
        h1 {
            text-align: center;
            color: #2980b9;
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 10px 12px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 0.95em;
        }
        th {
            background: #2980b9;
            color: white;
        }
        .btn {
            padding: 6px 12px;
            background: #2980b9;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #1f6391;
        }
        .btn-delete {
            background: #c0392b;
        }
        .btn-delete:hover {
            background: #922b22;
        }
        .add-btn {
            margin-bottom: 15px;
            display: inline-block;
        }
        a.back-link {
            display: inline-block;
            margin-top: 20px;
            color: #2980b9;
            text-decoration: none;
            font-weight: bold;
        }
        a.back-link:hover {
            text-decoration: underline;
        }
        .message-box {
            background:#d4edda;
            color:#155724;
            padding:10px 15px;
            margin-bottom:20px;
            border-radius:4px;
        }
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top:0; left:0;
            width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        .modal {
            background: white;
            max-width: 500px;
            margin: 80px auto;
            padding: 20px 30px;
            border-radius: 8px;
            position: relative;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }
        .modal h2 {
            margin-top: 0;
            color: #2980b9;
        }
        .modal label {
            display: block;
            margin: 12px 0 6px;
            font-weight: bold;
            font-size: 0.9em;
        }
        .modal input,
        .modal select {
            width: 100%;
            padding: 8px 10px;
            box-sizing: border-box;
            font-size: 1em;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .modal-buttons {
            margin-top: 20px;
            text-align: right;
        }
        .modal-buttons button {
            padding: 8px 14px;
            font-size: 1em;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-save {
            background: #27ae60;
            color: white;
            margin-left: 10px;
        }
        .btn-cancel {
            background: #bdc3c7;
            color: #333;
        }
        .modal-close {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 22px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
        .modal-close:hover {
            color: #333;
        }
        /* Responsive */
        @media (max-width: 600px) {
            table, thead, tbody, th, td, tr { display: block; }
            th { display: none; }
            td {
                border: none;
                border-bottom: 1px solid #eee;
                padding-left: 50%;
                position: relative;
                font-size: 0.95em;
            }
            td::before {
                position: absolute;
                left: 15px;
                width: 45%;
                font-weight: bold;
                white-space: nowrap;
                content: attr(data-label);
            }
        }
    </style>
</head>
<body>

    <h1>Student Records</h1>

    <?php if (!empty($msg)): ?>
        <div class="message-box"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <button class="btn add-btn" onclick="openModal()">+ Add Student</button>

    <table>
        <thead>
            <tr>
                <th>Admission No</th>
                <th>Birth Cert</th>
                <th>Name</th>
                <th>DOB</th>
                <th>Gender</th>
                <th>Class</th>
                <th>Term</th>
                <th>Religion</th>
                <th>Guardian</th>
                <th>Phone</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td data-label="Admission No"><?= htmlspecialchars($row['admission_no']) ?></td>
                    <td data-label="Birth Cert"><?= htmlspecialchars($row['birth_cert']) ?></td>
                    <td data-label="Name"><?= htmlspecialchars($row['name']) ?></td>
                    <td data-label="DOB"><?= htmlspecialchars($row['dob']) ?></td>
                    <td data-label="Gender"><?= htmlspecialchars($row['gender']) ?></td>
                    <td data-label="Class"><?= htmlspecialchars($row['class']) ?></td>
                    <td data-label="Term"><?= htmlspecialchars($row['term']) ?></td>
                    <td data-label="Religion"><?= htmlspecialchars($row['religion']) ?></td>
                    <td data-label="Guardian"><?= htmlspecialchars($row['guardian']) ?></td>
                    <td data-label="Phone"><?= htmlspecialchars($row['phone']) ?></td>
                    <td data-label="Created At"><?= htmlspecialchars($row['created_at']) ?></td>
                    <td data-label="Actions">
                        <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                            <button class="btn" onclick="openModal('<?= htmlspecialchars($row['admission_no']) ?>')">Edit</button>
                            <a href="?delete=<?= urlencode($row['admission_no']) ?>" class="btn btn-delete" onclick="return confirm('Delete this student?')">Delete</a>
                            <a href="?promote=<?= urlencode($row['admission_no']) ?>" class="btn" onclick="return confirm('Promote this student?')">Promote</a>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <a href="../php/master_panel.php" class="back-link">← Back to Master Panel</a>

    <!-- Modal -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <span class="modal-close" onclick="closeModal()" title="Close">&times;</span>
            <h2 id="modalTitle">Add/Edit Student</h2>
            <form id="studentForm" method="POST" enctype="multipart/form-data" autocomplete="off">
                <label for="admission_no">Admission No</label>
                <input type="text" name="admission_no" id="admission_no" required maxlength="20" />

                <label for="birth_cert">Birth Certificate</label>
                <input type="text" name="birth_cert" id="birth_cert" required maxlength="100" />

                <label for="name">Name</label>
                <input type="text" name="name" id="name" required maxlength="100" />

                <label for="dob">Date of Birth</label>
                <input type="date" name="dob" id="dob" />

                <label for="gender">Gender</label>
                <select name="gender" id="gender" required>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>

                <label for="class">Class</label>
                <select name="class" id="class" required>
                    <option value="babyclass">Baby Class</option>
                    <option value="intermediate">Intermediate</option>
                    <option value="PP1">PP1</option>
                    <option value="PP2">PP2</option>
                    <option value="grade1">Grade 1</option>
                    <option value="grade2">Grade 2</option>
                    <option value="grade3">Grade 3</option>
                    <option value="grade4">Grade 4</option>
                    <option value="grade5">Grade 5</option>
                    <option value="grade6">Grade 6</option>
                </select>

                <label for="term">Term</label>
                <select name="term" id="term" required>
                    <option value="term1">Term 1</option>
                    <option value="term2">Term 2</option>
                    <option value="term3">Term 3</option>
                </select>

                <label for="religion">Religion</label>
                <select name="religion" id="religion">
                    <option value="">-- Select --</option>
                    <option value="christian">Christian</option>
                    <option value="muslim">Muslim</option>
                    <option value="other">Other</option>
                </select>

                <label for="guardian">Guardian Name</label>
                <input type="text" name="guardian" id="guardian" maxlength="100" />

                <label for="phone">Phone</label>
                <input type="number" name="phone" id="phone" min="0" />

                <label for="student_photo">Student Photo (optional)</label>
                <input type="file" name="student_photo" id="student_photo" accept="image/*" />

                <div class="modal-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-save">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modalOverlay = document.getElementById('modalOverlay');
        const form = document.getElementById('studentForm');

        function openModal(admissionNo = '') {
            form.reset();
            if (admissionNo) {
                fetch('student_records.php?admission_no=' + encodeURIComponent(admissionNo))
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) return alert(data.error);
                        document.getElementById('admission_no').value = data.admission_no;
                        document.getElementById('admission_no').readOnly = true;
                        document.getElementById('birth_cert').value = data.birth_cert || '';
                        document.getElementById('name').value = data.name || '';
                        document.getElementById('dob').value = data.dob || '';
                        document.getElementById('gender').value = data.gender || 'male';
                        document.getElementById('class').value = data.class || 'babyclass';
                        document.getElementById('term').value = data.term || 'term1';
                        document.getElementById('religion').value = data.religion || '';
                        document.getElementById('guardian').value = data.guardian || '';
                        document.getElementById('phone').value = data.phone || '';
                    })
                    .catch(() => alert('Error fetching student data.'));
            } else {
                document.getElementById('admission_no').readOnly = false;
            }
            modalOverlay.style.display = 'block';
        }

        function closeModal() {
            modalOverlay.style.display = 'none';
        }

        modalOverlay.addEventListener('click', e => {
            if (e.target === modalOverlay) closeModal();
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>
