<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
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
    <link rel="stylesheet" href="../style/style-sheet.css">
        <link rel="website icon" type="png" href="../images/school-logo.jpg">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
    /* ===== Manage Students Page Styles ===== */

.manage-container {
    background: #fff;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    max-width: 1200px;
    margin: 0 auto 2rem auto;
}

/* Centered page title with icon */
.manage-container h2 {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.8rem;
    font-weight: 600;
    color: #1cc88a;
    margin-bottom: 1.5rem;
    text-align: center;
}
.manage-container h2::before {
    content: '\f234'; /* boxicons user icon Unicode */
    font-family: 'boxicons' !important;
    font-weight: 900;
    color: #4e73df;
    font-size: 2rem;
}

/* Filter dropdown */
#class-filter {
    padding: 0.5rem 1rem;
    font-size: 1rem;
    border: 1.8px solid #4e73df;
    border-radius: 6px;
    margin-bottom: 1.5rem;
    outline: none;
    transition: border-color 0.3s ease;
    cursor: pointer;
}
#class-filter:focus {
    border-color: #1cc88a;
}

/* Styled table */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 1rem;
}
table thead tr {
    background: linear-gradient(135deg, #4e73df, #1cc88a);
    color: white;
}
table th, table td {
    padding: 0.75rem 1rem;
    border: 1px solid #ddd;
    text-align: left;
    vertical-align: middle;
}
table tbody tr:hover {
    background-color: #f0f9ff;
}

/* Buttons inside table */
.edit-btn, .delete-btn {
    background: linear-gradient(135deg, #4e73df, #1cc88a);
    color: white;
    border: none;
    padding: 0.3rem 0.7rem;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s ease;
    margin-right: 0.4rem;
}
.edit-btn:hover {
    background: linear-gradient(135deg, #1cc88a, #4e73df);
}
.delete-btn {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
}
.delete-btn:hover {
    background: linear-gradient(135deg, #c0392b, #e74c3c);
}

/* Modal styles */
.modal {
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1rem;
}
.modal-content {
    background: #fff;
    border-radius: 10px;
    padding: 2rem;
    width: 100%;
    max-width: 600px;
    position: relative;
    box-shadow: 0 6px 15px rgba(0,0,0,0.2);
    max-height: 90vh;
    overflow-y: auto;
}

/* Close button */
.close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 1.8rem;
    font-weight: 700;
    cursor: pointer;
    color: #999;
    transition: color 0.3s ease;
}
.close:hover {
    color: #333;
}

/* Modal form labels with icon */
#edit-form label {
    font-weight: 600;
    color: #1cc88a;
    margin-bottom: 0.4rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.95rem;
}

/* Add icons to labels */
#edit-form label:nth-of-type(1)::before { content: '\f234'; font-family: 'boxicons'; margin-right: 0.3rem; } /* Admission No (disabled) */
#edit-form label:nth-of-type(2)::before { content: '\f24d'; font-family: 'boxicons'; margin-right: 0.3rem; } /* Birth Certificate */
#edit-form label:nth-of-type(3)::before { content: '\f234'; font-family: 'boxicons'; margin-right: 0.3rem; } /* Name */
#edit-form label:nth-of-type(4)::before { content: '\f073'; font-family: 'boxicons'; margin-right: 0.3rem; } /* DOB */
#edit-form label:nth-of-type(5)::before { content: '\f222'; font-family: 'boxicons'; margin-right: 0.3rem; } /* Gender */
#edit-form label:nth-of-type(6)::before { content: '\f4d8'; font-family: 'boxicons'; margin-right: 0.3rem; } /* Class */
#edit-form label:nth-of-type(7)::before { content: '\f073'; font-family: 'boxicons'; margin-right: 0.3rem; } /* Term */
#edit-form label:nth-of-type(8)::before { content: '\f51e'; font-family: 'boxicons'; margin-right: 0.3rem; } /* Religion */
#edit-form label:nth-of-type(9)::before { content: '\f4d9'; font-family: 'boxicons'; margin-right: 0.3rem; } /* Guardian */
#edit-form label:nth-of-type(10)::before { content: '\f2b6'; font-family: 'boxicons'; margin-right: 0.3rem; } /* Phone */

/* Inputs and selects in modal */
#edit-form input[type="text"],
#edit-form input[type="date"],
#edit-form select {
    width: 100%;
    padding: 0.5rem 0.8rem;
    margin-bottom: 1rem;
    border: 1.8px solid #4e73df;
    border-radius: 6px;
    font-size: 1rem;
    outline: none;
    transition: border-color 0.3s ease;
}
#edit-form input[type="text"]:focus,
#edit-form input[type="date"]:focus,
#edit-form select:focus {
    border-color: #1cc88a;
}

/* Submit button in modal */
#edit-form button[type="submit"] {
    background: linear-gradient(135deg, #4e73df, #1cc88a);
    color: white;
    border: none;
    padding: 0.7rem 1.5rem;
    border-radius: 8px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    display: block;
    margin: 0 auto;
    transition: background 0.3s ease;
}
#edit-form button[type="submit"]:hover {
    background: linear-gradient(135deg, #1cc88a, #4e73df);
}

@media (max-width: 768px) {
  #sidebar {
    display: none !important;  /* Hide sidebar */
  }

  main.content {
    margin-left: 0 !important; /* Remove sidebar space */
    width: 100% !important;    /* Take full width */
    padding: 1rem;             /* Optional: add some padding */
  }

  .dashboard-container {
    display: block; /* remove any flex to stack elements */
  }
}

</style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        <main class="content">
            <div class="manage-container">
    <h2><i class='bx bxs-user-account'></i> Manage Students</h2>


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

document.addEventListener("DOMContentLoaded", function () {
    /* ===== Real-time clock ===== */
    function updateClock() {
        const clockElement = document.getElementById('realTimeClock');
        if (clockElement) { 
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
            // silent logout
            window.location.href = 'logout.php';  // Change this to your actual logout URL
        }, 300000); // 5 minutes
    }

    ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetLogoutTimer);
    });

    resetLogoutTimer();
});
</script>


</body>
</html>



