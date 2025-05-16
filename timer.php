<?php
session_start();
if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "attendance_system");
$employee_id = $_SESSION['employee_id'];
$today = date('Y-m-d');

// Fetch today's attendance record for employee
$stmt = $conn->prepare("SELECT id, start_time, break_start_time, break_end_time, stop_time FROM attendance WHERE employee_id = ? AND date = ?");
$stmt->bind_param("is", $employee_id, $today);
$stmt->execute();
$result = $stmt->get_result();
$attendance = $result->fetch_assoc();

function formatTime($datetime) {
    return $datetime ? date('H:i:s', strtotime($datetime)) : 'Not set';
}

function getTotalWorkTime($start, $stop, $breakStart, $breakEnd) {
    if (!$start || !$stop) return 'N/A';  // can't calculate without start and stop

    $start_ts = strtotime($start);
    $stop_ts = strtotime($stop);

    $break_duration = 0;
    if ($breakStart && $breakEnd) {
        $break_duration = strtotime($breakEnd) - strtotime($breakStart);
        if ($break_duration < 0) $break_duration = 0; // sanity check
    }

    $work_seconds = ($stop_ts - $start_ts) - $break_duration;
    if ($work_seconds < 0) $work_seconds = 0; // sanity check

    // format seconds to HH:MM:SS
    return gmdate("H:i:s", $work_seconds);
}

// Fetch all attendance records for employee (latest 10)
$stmt_all = $conn->prepare("SELECT date, start_time, break_start_time, break_end_time, stop_time FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 10");
$stmt_all->bind_param("i", $employee_id);
$stmt_all->execute();
$result_all = $stmt_all->get_result();

?>

<!DOCTYPE html>
<html>
<head>
    <title>Employee Timer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

    <link rel="stylesheet" href="timer.css">
</head>
<body class="container mt-5">

    <h2>Welcome Employee #<?php echo $employee_id; ?></h2>

    <?php if (!$attendance): ?>
        <form method="post" action="action.php">
            <button type="submit" name="start_work" class="btn btn-success">Start Work</button>
        </form>

    <?php elseif ($attendance['stop_time']): ?>
        <p>Work stopped at: <?php echo formatTime($attendance['stop_time']); ?></p>
        <a href="logout.php" class="btn btn-primary">Logout</a>

    <?php else: ?>
        <p>Work started at: <?php echo formatTime($attendance['start_time']); ?></p>

        <?php if (!$attendance['break_start_time'] || ($attendance['break_end_time'] && strtotime($attendance['break_end_time']) < strtotime($attendance['break_start_time']))): ?>
            <form method="post" action="action.php">
                <button type="submit" name="start_break" class="btn btn-warning">Start Break</button>
            </form>
        <?php elseif (!$attendance['break_end_time'] || strtotime($attendance['break_end_time']) < strtotime($attendance['break_start_time'])): ?>
            <p>Break started at: <?php echo formatTime($attendance['break_start_time']); ?></p>
            <form method="post" action="action.php">
                <button type="submit" name="stop_break" class="btn btn-info">Stop Break</button>
            </form>
        <?php endif; ?>

        <form method="post" action="action.php" class="mt-3">
            <button type="submit" name="stop_work" class="btn btn-danger">Stop Work</button>
        </form>
        <a href="logout.php" class="btn btn-secondary mt-3">Logout</a>
    <?php endif; ?>

    <hr>

    <h3>Last 10 Attendance Records</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Date</th>
                <th>Start Work</th>
                <th>Break Start</th>
                <th>Break End</th>
                <th>Stop Work</th>
                <th>Total Work Time</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result_all->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['date']); ?></td>
                <td><?php echo formatTime($row['start_time']); ?></td>
                <td><?php echo formatTime($row['break_start_time']); ?></td>
                <td><?php echo formatTime($row['break_end_time']); ?></td>
                <td><?php echo formatTime($row['stop_time']); ?></td>
                <td><?php echo getTotalWorkTime($row['start_time'], $row['stop_time'], $row['break_start_time'], $row['break_end_time']); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</body>
</html>
