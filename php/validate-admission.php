<?php
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = trim($_POST['admission_no']);

    // Query to check current students
    $stmt_current = $conn->prepare("SELECT admission_no FROM student_records WHERE admission_no = ?");
    $stmt_current->bind_param("s", $admission_no);
    $stmt_current->execute();
    $result_current = $stmt_current->get_result();

    // Query to check graduated students (only if not found in current)
    if ($result_current->num_rows > 0) {
        echo json_encode(["status" => "success", "message" => "✅ Admission Number verified (Current Student)."]);
    } else {
        $stmt_graduated = $conn->prepare("SELECT admission_no FROM graduated_students WHERE admission_no = ?");
        $stmt_graduated->bind_param("s", $admission_no);
        $stmt_graduated->execute();
        $result_graduated = $stmt_graduated->get_result();

        if ($result_graduated->num_rows > 0) {
            echo json_encode(["status" => "success", "message" => "✅ Admission Number verified (Graduated Student)."]);
        } else {
            echo json_encode(["status" => "error", "message" => "⚠️ Admission Number not found in any records!"]);
        }

        $stmt_graduated->close();
    }

    $stmt_current->close();
    $conn->close();
}
?>
