<?php
include 'db.php'; // Include database connection

$classes = ['babyclass', 'intermediate', 'PP1', 'PP2', 'grade1', 'grade2', 'grade3', 'grade4', 'grade5', 'grade6'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $admission_no = $_POST['admission_no'];

        if ($action === 'delete') {
            // Delete student and related records
            $related_tables = [
                'book_purchases',
                'lunch_fees',
                'lunch_fee_transactions',
                'school_fees',
                'school_fee_transactions',
                'others',
                'uniform_purchases'
            ];

            foreach ($related_tables as $table) {
                $stmt = $conn->prepare("DELETE FROM $table WHERE admission_no = ?");
                $stmt->bind_param("s", $admission_no);
                $stmt->execute();
            }

            $stmt = $conn->prepare("DELETE FROM student_records WHERE admission_no = ?");
            $stmt->bind_param("s", $admission_no);
            $stmt->execute();

            echo "Student and related records removed successfully!";
            exit;

        } elseif ($action === 'update') {
            // Get all editable fields
            $name       = $_POST['name'];
            $dob        = $_POST['dob'];
            $gender     = $_POST['gender'];
            $class      = $_POST['class'];
            $term       = $_POST['term'];
            $religion   = $_POST['religion'];
            $guardian   = $_POST['guardian'];
            $phone      = $_POST['phone'];
            $birth_cert = $_POST['birth_cert']; // NEW FIELD

            // Update query now includes birth_cert
            $stmt = $conn->prepare("UPDATE student_records 
                SET name=?, dob=?, gender=?, class=?, term=?, religion=?, guardian=?, phone=?, birth_cert=?
                WHERE admission_no=?");
            $stmt->bind_param("ssssssssss", $name, $dob, $gender, $class, $term, $religion, $guardian, $phone, $birth_cert, $admission_no);
            $stmt->execute();

            echo "Student details updated successfully!";
            exit;
        }

    } elseif (isset($_POST['admission_no'])) {
        // Fetch student record for editing
        $admission_no = $_POST['admission_no'];
        $stmt = $conn->prepare("SELECT * FROM student_records WHERE admission_no = ?");
        $stmt->bind_param("s", $admission_no);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($student = $result->fetch_assoc()) {
            // Replace NULLs with empty strings for all fields
            foreach ($student as $key => $value) {
                $student[$key] = $value ?? '';
            }
            echo json_encode($student);
        } else {
            echo json_encode(["error" => "Student not found"]);
        }
        exit;
    }
}

// Load student list for display
$class_filter = isset($_GET['class']) ? $_GET['class'] : '';
$sql = "SELECT * FROM student_records";
if ($class_filter != '') {
    $sql .= " WHERE class = ?";
}

$stmt = $conn->prepare($sql);
if ($class_filter != '') {
    $stmt->bind_param("s", $class_filter);
}
$stmt->execute();
$result = $stmt->get_result();
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="website icon" type="png" href="photos/Logo.jpg">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        <main class="content">
            <div class="manage-container">
    <h2>Manage Students</h2>

    <label for="class-filter">Filter by Class:</label>
    <select id="class-filter">
        <option value="">All Classes</option>
        <?php foreach ($classes as $class): ?>
            <option value="<?= $class ?>" <?= $class_filter == $class ? 'selected' : '' ?>><?= $class ?></option>
        <?php endforeach; ?>
    </select>

    <table border="1">
        <thead>
            <tr>
                <th>Admission No</th>
                <th>Name</th>
                <th>Class</th>
                <th>Term</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($student = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $student['admission_no']; ?></td>
                    <td><?= $student['name']; ?></td>
                    <td><?= $student['class']; ?></td>
                    <td><?= $student['term']; ?></td>
                    <td>
                        <button class="edit-btn" data-id="<?= $student['admission_no']; ?>">Edit</button>
                        <button class="delete-btn" data-id="<?= $student['admission_no']; ?>">Delete</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="modal" style="display:none;">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h3>Edit Student</h3>
    <form id="edit-form">
      <input type="hidden" id="edit-admission">

      <label>Admission No:</label>
      <input type="text" id="edit-admission-display" disabled>

      <label>Birth Certificate No:</label>
      <input type="text" id="edit-birth-cert">

      <label>Name:</label>
      <input type="text" id="edit-name" required>

      <label>Date of Birth:</label>
      <input type="date" id="edit-dob">

      <label>Gender:</label>
      <select id="edit-gender">
        <option value="">--Select--</option>
        <option value="male">Male</option>
        <option value="female">Female</option>
      </select>

      <label>Class:</label>
      <select id="edit-class">
        <?php foreach ($classes as $class): ?>
          <option value="<?= $class ?>"><?= $class ?></option>
        <?php endforeach; ?>
      </select>

      <label>Term:</label>
      <select id="edit-term">
        <option value="term1">Term 1</option>
        <option value="term2">Term 2</option>
        <option value="term3">Term 3</option>
      </select>

      <label>Religion:</label>
      <select id="edit-religion">
        <option value="">--Select--</option>
        <option value="christian">Christian</option>
        <option value="muslim">Muslim</option>
        <option value="other">Other</option>
      </select>

      <label>Guardian:</label>
      <input type="text" id="edit-guardian" required>

      <label>Phone:</label>
      <input type="text" id="edit-phone" required>

      <button type="submit">Save Changes</button>
    </form>
  </div>
</div>

    </main>
</div>

    <?php include '../includes/footer.php'; ?>


<script>
$(document).ready(function () {
    // Filter by class
    $("#class-filter").change(function () {
        let selectedClass = $(this).val();
        window.location.href = "manage-students.php?class=" + selectedClass;
    });

    // Open edit modal and populate fields
    $(".edit-btn").click(function () {
        let admissionNo = $(this).data("id");

        $.post("manage-students.php", { admission_no: admissionNo }, function (response) {
            let student = JSON.parse(response);

            if (student.error) {
                alert(student.error);
            } else {
                $("#edit-admission").val(student.admission_no || '');
                $("#edit-admission-display").val(student.admission_no || '');
                $("#edit-name").val(student.name || '');
                $("#edit-dob").val(student.dob || '');
                $("#edit-class").val(student.class || '');
                $("#edit-term").val(student.term || '');
                $("#edit-guardian").val(student.guardian || '');
                $("#edit-phone").val(student.phone || '');

                // Optional fields
                $("#edit-birth-cert").val(student.birth_cert || '');
                $("#edit-religion").val(student.religion || '');
                $("#edit-gender").val(student.gender || '');

                // Show modal
                $("#edit-modal").show();
            }
        });
    });

    // Close modal
    $(".close").click(function () {
        $("#edit-modal").hide();
    });

    // Submit edit form
    $("#edit-form").submit(function (e) {
        e.preventDefault();

        let data = {
            action: "update",
            admission_no: $("#edit-admission").val(),
            name: $("#edit-name").val(),
            dob: $("#edit-dob").val(),
            class: $("#edit-class").val(),
            term: $("#edit-term").val(),
            guardian: $("#edit-guardian").val(),
            phone: $("#edit-phone").val(),

            // Additional fields
            birth_cert: $("#edit-birth-cert").val(),
            religion: $("#edit-religion").val(),
            gender: $("#edit-gender").val()
        };

        $.post("manage-students.php", data, function (response) {
            alert(response);
            location.reload();
        });
    });

    // Delete student
    $(".delete-btn").click(function () {
        if (!confirm("Are you sure you want to delete this student and all related records?")) return;

        let admissionNo = $(this).data("id");

        $.post("manage-students.php", {
            action: "delete",
            admission_no: admissionNo
        }, function (response) {
            alert(response);
            location.reload();
        });
    });
});
</script>

<!-- Real-time clock script -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    function updateClock() {
        const clockElement = document.getElementById('realTimeClock');
        if (clockElement) {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            clockElement.textContent = timeString;
        }
    }

    updateClock(); // Initial call
    setInterval(updateClock, 1000); // Update every second
});
</script>

</body>
</html>



