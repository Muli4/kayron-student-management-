<?php
require_once 'db.php'; // Ensure you include the correct database connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = trim($_POST['admission_no']);

    // Check if the admission number exists
    $stmt = $conn->prepare("SELECT * FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "⚠️ Admission Number not found!"]);
        exit;
    } else {
        echo json_encode(["status" => "success", "message" => "✅ Admission Number verified."]);
    }

    $stmt->close();
    $conn->close();
}
?>
