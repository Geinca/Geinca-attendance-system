<?php
include 'db_config.php';


$today = date('Y-m-d');

function redirect() {
    header("Location: ./dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if record exists for today
    $stmt = $conn->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
    $stmt->bind_param("is", $employee_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance = $result->fetch_assoc();

    if (isset($_POST['start_work'])) {
        if (!$attendance) {
            // Insert start time
           $start = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
            $stmt = $conn->prepare("INSERT INTO attendance (employee_id, date, start_time) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $employee_id, $today, $start);
            $stmt->execute();
        }
        redirect();
    }

    if (!$attendance) {
        redirect(); // no record yet for other actions
    }

    $attendance_id = $attendance['id'];

    if (isset($_POST['start_break'])) {
        $start_break = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE attendance SET break_start_time = ? WHERE id = ?");
        $stmt->bind_param("si", $start_break, $attendance_id);
        $stmt->execute();
        redirect();
    }

    if (isset($_POST['stop_break'])) {
        $stop_break = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE attendance SET break_end_time = ? WHERE id = ?");
        $stmt->bind_param("si", $stop_break, $attendance_id);
        $stmt->execute();
        redirect();
    }

    if (isset($_POST['stop_work'])) {
        $stop_work = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE attendance SET stop_time = ? WHERE id = ?");
        $stmt->bind_param("si", $stop_work, $attendance_id);
        $stmt->execute();
        redirect();
    }
}
?>
