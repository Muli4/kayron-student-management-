<?php
session_start();
include './db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admission_no = trim($_POST['admission_no'] ?? '');
    $payment_type = $_POST['payment_type'] ?? 'Cash';

    $valid_payment_types = ['Cash', 'Card', 'Bank Transfer', 'Other', 'M-Pesa'];
    if (!in_array($payment_type, $valid_payment_types)) {
        $payment_type = 'Other';
    }
    $uniform_payment_type = $payment_type === 'M-Pesa' ? 'Other' : $payment_type;

    if (empty($admission_no)) {
        die(json_encode(["status" => "error", "message" => "Missing admission number."]));
    }

    // 1. Get student
    $check = $conn->prepare("SELECT name FROM student_records WHERE admission_no = ?");
    $check->bind_param("s", $admission_no);
    $check->execute();
    $result = $check->get_result();
    $student = $result->fetch_assoc();
    $check->close();

    if (!$student) {
        die(json_encode(["status" => "error", "message" => "Admission number not found."]));
    }

    $student_name = $student['name'];

    // 2. Get latest term
    $term_result = $conn->query("SELECT term_number FROM terms ORDER BY id DESC LIMIT 1");
    $latest_term = $term_result->fetch_assoc();
    $term_number = $latest_term ? $latest_term['term_number'] : 'X';
    $receipt_number = "KJS-T{$term_number}-" . strtoupper(substr(uniqid(), -5));

    // 3. Helper function
    function postVal($arr, $key, $default = '') {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    // Initialize total paid trackers
    $total_paid_books = 0;
    $total_paid_uniforms = 0;

    // 4. Book purchases
    if (!empty($_POST['books'])) {
        foreach ($_POST['books'] as $book_name => $book) {
            if (empty($book['selected'])) continue;

            $quantity = (int)postVal($book, 'quantity', 0);
            $price = (float)postVal($book, 'price', 0);
            $amount_paid = (float)postVal($book, 'amount_paid', 0);

            if ($quantity > 0 && !empty($book_name)) {
                $total_paid_books += $amount_paid;

                // Check for existing balance
                $existing = $conn->prepare("SELECT id, amount_paid, total_price FROM book_purchases 
                                            WHERE admission_no = ? AND book_name = ? AND balance > 0 
                                            ORDER BY id DESC LIMIT 1");
                $existing->bind_param("ss", $admission_no, $book_name);
                $existing->execute();
                $existing_result = $existing->get_result();
                $row = $existing_result->fetch_assoc();
                $existing->close();

                if ($row) {
                    $old_paid = $row['amount_paid'];
                    $total_price = $row['total_price'];
                    $record_id = $row['id'];
                    $old_balance = $total_price - $old_paid;

                    $leftover = $amount_paid - $old_balance;

                    if ($leftover >= 0) {
                        // Update old record
                        $new_paid = $old_paid + $old_balance;
                        $update = $conn->prepare("UPDATE book_purchases SET amount_paid = ?, balance = 0 WHERE id = ?");
                        $update->bind_param("di", $new_paid, $record_id);
                        $update->execute();
                        $update->close();

                        // Use remaining for new
                        $new_total = $quantity * $price;
                        $new_balance = $new_total - $leftover;

                        $insert = $conn->prepare("INSERT INTO book_purchases 
                            (receipt_number, admission_no, name, book_name, quantity, total_price, amount_paid, balance, payment_type) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $insert->bind_param("ssssiddss", $receipt_number, $admission_no, $student_name, $book_name, $quantity, $new_total, $leftover, $new_balance, $payment_type);
                        $insert->execute();
                        $insert->close();
                    } else {
                        // Partial payment, just update old record
                        $new_paid = $old_paid + $amount_paid;
                        $new_balance = $total_price - $new_paid;

                        $update = $conn->prepare("UPDATE book_purchases SET amount_paid = ?, balance = ? WHERE id = ?");
                        $update->bind_param("ddi", $new_paid, $new_balance, $record_id);
                        $update->execute();
                        $update->close();
                    }
                } else {
                    // No old balance
                    $total = $quantity * $price;
                    $balance = $total - $amount_paid;

                    $insert = $conn->prepare("INSERT INTO book_purchases 
                        (receipt_number, admission_no, name, book_name, quantity, total_price, amount_paid, balance, payment_type) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insert->bind_param("ssssiddss", $receipt_number, $admission_no, $student_name, $book_name, $quantity, $total, $amount_paid, $balance, $payment_type);
                    $insert->execute();
                    $insert->close();
                }
            }
        }
    }

    // 5. Uniform purchases
    if (!empty($_POST['uniforms'])) {
        foreach ($_POST['uniforms'] as $uniform) {
            if (empty($uniform['selected'])) continue;

            $uniform_type = postVal($uniform, 'type');
            $size = postVal($uniform, 'size');
            $quantity = (int)postVal($uniform, 'quantity', 0);
            $price = (float)postVal($uniform, 'price', 0);
            $amount_paid = (float)postVal($uniform, 'amount_paid', 0);

            if ($quantity > 0 && !empty($uniform_type)) {
                $total_paid_uniforms += $amount_paid;

                // Check previous balance
                $existing = $conn->prepare("SELECT id, amount_paid, total_price FROM uniform_purchases 
                                            WHERE admission_no = ? AND uniform_type = ? AND balance > 0 
                                            ORDER BY id DESC LIMIT 1");
                $existing->bind_param("ss", $admission_no, $uniform_type);
                $existing->execute();
                $existing_result = $existing->get_result();
                $row = $existing_result->fetch_assoc();
                $existing->close();

                if ($row) {
                    $old_paid = $row['amount_paid'];
                    $total_price = $row['total_price'];
                    $record_id = $row['id'];
                    $old_balance = $total_price - $old_paid;

                    $leftover = $amount_paid - $old_balance;

                    if ($leftover >= 0) {
                        $new_paid = $old_paid + $old_balance;
                        $update = $conn->prepare("UPDATE uniform_purchases SET amount_paid = ?, balance = 0 WHERE id = ?");
                        $update->bind_param("di", $new_paid, $record_id);
                        $update->execute();
                        $update->close();

                        $new_total = $quantity * $price;
                        $new_balance = $new_total - $leftover;

                        $insert = $conn->prepare("INSERT INTO uniform_purchases 
                            (receipt_number, name, admission_no, uniform_type, size, quantity, total_price, amount_paid, balance, payment_type) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $insert->bind_param("sssssiddds", $receipt_number, $student_name, $admission_no, $uniform_type, $size, $quantity, $new_total, $leftover, $new_balance, $uniform_payment_type);
                        $insert->execute();
                        $insert->close();
                    } else {
                        $new_paid = $old_paid + $amount_paid;
                        $new_balance = $total_price - $new_paid;

                        $update = $conn->prepare("UPDATE uniform_purchases SET amount_paid = ?, balance = ? WHERE id = ?");
                        $update->bind_param("ddi", $new_paid, $new_balance, $record_id);
                        $update->execute();
                        $update->close();
                    }
                } else {
                    $total = $quantity * $price;
                    $balance = $total - $amount_paid;

                    $insert = $conn->prepare("INSERT INTO uniform_purchases 
                        (receipt_number, name, admission_no, uniform_type, size, quantity, total_price, amount_paid, balance, payment_type) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insert->bind_param("sssssiddds", $receipt_number, $student_name, $admission_no, $uniform_type, $size, $quantity, $total, $amount_paid, $balance, $uniform_payment_type);
                    $insert->execute();
                    $insert->close();
                }
            }
        }
    }

    // 6. Log total transaction in purchase_transactions table
    $total_paid = $total_paid_books + $total_paid_uniforms;

    $log = $conn->prepare("INSERT INTO purchase_transactions (receipt_number, admission_no, student_name, total_amount_paid, payment_type) VALUES (?, ?, ?, ?, ?)");
    $log->bind_param("ssdss", $receipt_number, $admission_no, $student_name, $total_paid, $payment_type);
    $log->execute();
    $log->close();

    // Prepare fees JSON for GET parameter
    $fees_json = urlencode(json_encode($fees));

    // Redirect to receipt page with receipt details
    header("Location: purchase-receipt.php?receipt_number=$receipt_number&admission_no=$admission_no&payment_type=$payment_type&fees=$fees_json");
    exit;
}
?>
