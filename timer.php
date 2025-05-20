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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #019FE2;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: purple;
            --warning: #f8961e;
            --info: #4895ef;
            --light: #f8f9fa;
            --lightblue: #019FE2;
        }
        
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(1, 158, 226, 0.14);
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .time-display {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--dark);
            background: rgba(67, 97, 238, 0.1);
            padding: 8px 15px;
            border-radius: 8px;
            display: inline-block;
        }
        
        .btn-custom {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-start {
            background-color: var(--success);
            color: white;
        }

        .btn-start:hover{
            background: white;
            border:1px solid var(--success);
            color: var(--success);
        }
        
        .btn-stop {
            background-color: var(--danger);
            color: white;
            border:1px solid var(--danger);
        }
        
        .btn-stop:hover{
            background: white;
            border:1px solid var(--danger);
            color: var(--danger);
        }
        
        .btn-break {
            background-color: var(--warning);
            color: white;
            border:1px solid var(--warning);
        }

        .btn-break:hover{
            background: white;
            border:1px solid var(--warning);
            color: var(--warning);
        }
        
        .btn-end-break {
            background-color: var(--info);
            border: 1px solid var(--info);
            color: white;
        }
        
        .btn-end-break:hover{
            background: white;
            border: 1px solid var(--info);
            color: var(--info);
        }
        
        .btn-logout {
            background-color: var(--lightblue);
            color: white;
        }

        .btn-logout:hover{
            background: white;
            border:1px solid var(--lightblue);
            color: var(--lightblue);
        }
        
        .table-custom {
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        
        .table-custom thead {
            background-color: var(--primary);
            color: white;
        }
        
        .table-custom th {
            border: none;
            padding: 15px;
        }
        
        .table-custom td {
            padding: 12px 15px;
            vertical-align: middle;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-active {
            background-color: rgba(76, 201, 240, 0.2);
            color: var(--success);
        }
        
        .badge-break {
            background-color: rgba(248, 150, 30, 0.2);
            color: var(--warning);
        }
        
        .badge-inactive {
            background-color: rgba(247, 37, 133, 0.2);
            color: var(--danger);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="dashboard-card p-4 mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="fas fa-user-clock me-2"></i>Employee Dashboard
                </h2>
                <div class="status-badge <?php echo (!$attendance || $attendance['stop_time']) ? 'badge-inactive' : ($attendance['break_start_time'] && (!$attendance['break_end_time'] || strtotime($attendance['break_end_time']) < strtotime($attendance['break_start_time'])) ? 'badge-break' : 'badge-active'); ?>">
                    <?php echo (!$attendance || $attendance['stop_time']) ? 'Offline' : ($attendance['break_start_time'] && (!$attendance['break_end_time'] || strtotime($attendance['break_end_time']) < strtotime($attendance['break_start_time'])) ? 'On Break' : 'Active'); ?>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="dashboard-card p-4 h-100">
                        <h5 class="mb-4"><i class="fas fa-clock me-2"></i>Today's Status</h5>
                        
                        <?php if (!$attendance): ?>
                            <p class="text-muted mb-4">You haven't started working today</p>
                            <form method="post" action="action.php">
                                <button type="submit" name="start_work" class="btn btn-start btn-custom w-100">
                                    <i class="fas fa-play me-2"></i>Start Work
                                </button>
                            </form>
                            
                        <?php elseif ($attendance['stop_time']): ?>
                            <div class="mb-4">
                                <p class="mb-2">Work started at: <span class="time-display"><?php echo formatTime($attendance['start_time']); ?></span></p>
                                <p class="mb-2">Work stopped at: <span class="time-display"><?php echo formatTime($attendance['stop_time']); ?></span></p>
                                <p class="mb-2">Total work time: <span class="time-display"><?php echo getTotalWorkTime($attendance['start_time'], $attendance['stop_time'], $attendance['break_start_time'], $attendance['break_end_time']); ?></span></p>
                            </div>
                            <a href="logout.php" class="btn btn-logout btn-custom w-100">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                            
                        <?php else: ?>
                            <div class="mb-4">
                                <p class="mb-2">Work started at: <span class="time-display"><?php echo formatTime($attendance['start_time']); ?></span></p>
                                
                                <?php if ($attendance['break_start_time']): ?>
                                    <p class="mb-2">Break started at: <span class="time-display"><?php echo formatTime($attendance['break_start_time']); ?></span></p>
                                <?php endif; ?>
                                
                                <?php if ($attendance['break_end_time']): ?>
                                    <p class="mb-2">Break ended at: <span class="time-display"><?php echo formatTime($attendance['break_end_time']); ?></span></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-grid gap-3">
                                <?php if (!$attendance['break_start_time'] || ($attendance['break_end_time'] && strtotime($attendance['break_end_time']) < strtotime($attendance['break_start_time']))): ?>
                                    <form method="post" action="action.php">
                                        <button type="submit" name="start_break" class="btn btn-break btn-custom">
                                            <i class="fas fa-coffee me-2"></i>Start Break
                                        </button>
                                    </form>
                                <?php elseif (!$attendance['break_end_time'] || strtotime($attendance['break_end_time']) < strtotime($attendance['break_start_time'])): ?>
                                    <form method="post" action="action.php">
                                        <button type="submit" name="stop_break" class="btn btn-end-break btn-custom">
                                            <i class="fas fa-clock me-2"></i>End Break
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="post" action="action.php">
                                    <button type="submit" name="stop_work" class="btn btn-stop btn-custom">
                                        <i class="fas fa-stop me-2"></i>Stop Work
                                    </button>
                                </form>
                                
                                <a href="logout.php" class="btn btn-logout btn-custom">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="dashboard-card p-4 h-100">
                        <h5 class="mb-4"><i class="fas fa-calendar-alt me-2"></i>Recent Activity</h5>
                        <div class="table-responsive">
                            <table class="table table-custom table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result_all->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['date']); ?></td>
                                        <td>
                                            <?php if ($row['stop_time']): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger">Completed</span>
                                            <?php elseif ($row['break_start_time'] && (!$row['break_end_time'] || strtotime($row['break_end_time']) < strtotime($row['break_start_time']))): ?>
                                                <span class="badge bg-warning bg-opacity-10 text-warning">On Break</span>
                                            <?php elseif ($row['start_time']): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success">Working</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo getTotalWorkTime($row['start_time'], $row['stop_time'], $row['break_start_time'], $row['break_end_time']); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="dashboard-card p-4">
            <h5 class="mb-4"><i class="fas fa-history me-2"></i>Detailed Attendance Records</h5>
            <div class="table-responsive">
                <table class="table table-custom table-hover">
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
                        <?php 
                        // Reset pointer to beginning for second display
                        $result_all->data_seek(0); 
                        while ($row = $result_all->fetch_assoc()): ?>
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
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>