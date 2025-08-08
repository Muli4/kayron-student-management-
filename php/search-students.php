<?php
header('Content-Type: application/json');
include 'db.php';

if (!isset($_POST['query'])) {
    echo json_encode([]);
    exit;
}

$query = trim($_POST['query']);
$stmt = $conn->prepare("
    SELECT admission_no, name, class 
    FROM student_records 
    WHERE name LIKE CONCAT('%', ?, '%') 
       OR admission_no LIKE CONCAT('%', ?, '%') 
    LIMIT 10
");

if (!$stmt) {
    echo json_encode([]);
    exit;
}

$stmt->bind_param("ss", $query, $query);
$stmt->execute();
$result = $stmt->get_result();

$suggestions = [];
while ($row = $result->fetch_assoc()) {
    $suggestions[] = [
        'admission_no' => $row['admission_no'],
        'name' => $row['name'],
        'class' => $row['class']
    ];
}
echo json_encode($suggestions);
?>
