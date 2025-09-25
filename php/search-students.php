<?php
header('Content-Type: application/json');
include 'db.php';

if (!isset($_POST['query'])) {
    echo json_encode([]);
    exit;
}

$query = trim($_POST['query']);

$sql = "
    (
        SELECT admission_no, name, class AS class
        FROM student_records
        WHERE name LIKE CONCAT('%', ?, '%')
           OR admission_no LIKE CONCAT('%', ?, '%')
    )
    UNION
    (
        SELECT admission_no, name, class_completed AS class
        FROM graduated_students
        WHERE name LIKE CONCAT('%', ?, '%')
           OR admission_no LIKE CONCAT('%', ?, '%')
    )
    LIMIT 10
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['error' => 'SQL preparation failed']);
    exit;
}

// Bind 4 parameters: 2 for student_records, 2 for graduated_students
$stmt->bind_param("ssss", $query, $query, $query, $query);
$stmt->execute();
$result = $stmt->get_result();

$suggestions = [];
while ($row = $result->fetch_assoc()) {
    $suggestions[] = [
        'label' => $row['admission_no'] . ' - ' . $row['name'] . ' - ' . $row['class'],
        'admission_no' => $row['admission_no'],
        'name' => $row['name'],
        'class' => $row['class']
    ];
}

echo json_encode($suggestions);
?>
