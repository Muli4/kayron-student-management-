<?php
include 'db.php'; // Include database connection

// Handle update, delete, and fetch student details
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $id = $_POST['id'];

        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM student_records WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo "Student removed successfully!";
            exit;
        } elseif ($action === 'update') {
            $admission_no = $_POST['admission_no'];
            $name = $_POST['name'];
            $class = $_POST['class'];
            $term = $_POST['term'];

            $stmt = $conn->prepare("UPDATE student_records SET admission_no=?, name=?, class=?, term=? WHERE id=?");
            $stmt->bind_param("ssssi", $admission_no, $name, $class, $term, $id);
            $stmt->execute();
            echo "Student details updated successfully!";
            exit;
        }
    } elseif (isset($_POST['id'])) {
        // Fetch student details
        $id = $_POST['id'];
        $stmt = $conn->prepare("SELECT * FROM student_records WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($student = $result->fetch_assoc()) {
            echo json_encode($student);
        } else {
            echo json_encode(["error" => "Student not found"]);
        }
        exit;
    }
}

// Fetch students and filter by class if selected
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students</title>
    <link rel="stylesheet" href="../style/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<div class="heading-all">
    <h2 class="title">Kayron Junior School</h2>
</div>
<div class="container">
    <h2>Manage Students</h2>

    <label for="class-filter">Filter by Class:</label>
    <select id="class-filter">
        <option value="">All Classes</option>
        <?php
        $classes = ['babyclass', 'intermediate', 'PP1', 'PP2', 'grade1', 'grade2', 'grade3', 'grade4', 'grade5', 'grade6'];
        foreach ($classes as $class) {
            echo "<option value='$class'" . ($class_filter == $class ? " selected" : "") . ">$class</option>";
        }
        ?>
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

<!-- Edit Student Modal -->
<div id="edit-modal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit Student</h3>
        <form id="edit-form">
            <input type="hidden" id="edit-id">
            <label>Admission No:</label>
            <input type="text" id="edit-admission" required>
            <label>Name:</label>
            <input type="text" id="edit-name" required>
            <label>Class:</label>
            <select id="edit-class">
                <?php foreach ($classes as $class) { echo "<option value='$class'>$class</option>"; } ?>
            </select>
            <label>Term:</label>
            <input type="text" id="edit-term" required>
            <button type="submit">Save Changes</button>
        </form>
    </div>
</div>

        <div class="button-container">
            <button type="button" class="add-student-btn"><a href="./dashboard.php">Back to Dashboard</a></button>
        </div>

        <footer class="footer-dash">
            <p>&copy; <?= date("Y") ?> Kayron Junior School. All Rights Reserved.</p>
        </footer>
<script>
$(document).ready(function() {
    // Filter by class
    $("#class-filter").change(function() {
        let selectedClass = $(this).val();
        window.location.href = "manage-students.php?class=" + selectedClass;
    });

    // Open edit modal and fetch student details
    $(".edit-btn").click(function() {
        let studentId = $(this).data("id");

        $.post("manage-students.php", { id: studentId }, function(response) {
            let student = JSON.parse(response);
            if (student.error) {
                alert(student.error);
            } else {
                $("#edit-id").val(student.id);
                $("#edit-admission").val(student.admission_no);
                $("#edit-name").val(student.name);
                $("#edit-class").val(student.class);
                $("#edit-term").val(student.term);
                $("#edit-modal").show();
            }
        });
    });

    // Close modal
    $(".close").click(function() {
        $("#edit-modal").hide();
    });

    // Submit edit form
    $("#edit-form").submit(function(e) {
        e.preventDefault();
        let id = $("#edit-id").val();
        let admission = $("#edit-admission").val();
        let name = $("#edit-name").val();
        let studentClass = $("#edit-class").val();
        let term = $("#edit-term").val();

        $.post("manage-students.php", { action: "update", id: id, admission_no: admission, name: name, class: studentClass, term: term }, function(response) {
            alert(response);
            location.reload();
        });
    });

    // Delete student
    $(".delete-btn").click(function() {
        if (!confirm("Are you sure you want to delete this student?")) return;

        let studentId = $(this).data("id");
        $.post("manage-students.php", { action: "delete", id: studentId }, function(response) {
            alert(response);
            location.reload();
        });
    });
});
</script>

<style>
/* Basic Styles */
.container {
    flex: 1;
    width: 80%;
    margin: 20px auto;
    font-family: Arial, sans-serif;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th, td {
    padding: 10px;
    text-align: left;
    border: 1px solid #ddd;
}

th {
    background: #007bff;
    color: white;
}

.edit-btn {
    background: #28a745;
    color: white;
    border: none;
    padding: 5px 10px;
    cursor: pointer;
}

.delete-btn {
    background: #dc3545;
    color: white;
    border: none;
    padding: 5px 10px;
    cursor: pointer;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    padding: 20px;
    border-radius: 5px;
    text-align: center;
}

.close {
    cursor: pointer;
    float: right;
    font-size: 20px;
}
</style>

</body>
</html>
