<?php
date_default_timezone_set('Asia/Kolkata');
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "attendance_system");
$employee_id = $_SESSION['employee_id'];

// Get filter parameters
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'monthly';

// Fetch employee details
$stmt_name = $conn->prepare("SELECT username FROM employees WHERE id = ?");
$stmt_name->bind_param("i", $employee_id);
$stmt_name->execute();
$result_name = $stmt_name->get_result();
$employee = $result_name->fetch_assoc();
$employee_name = $employee['username'] ?? 'Employee';
$department = $employee['department'] ?? '';

// Calculate date ranges based on report type
if ($report_type == 'monthly') {
    $start_date = date('Y-m-01', strtotime($month));
    $end_date = date('Y-m-t', strtotime($month));
    $title = "Monthly Report for " . date('F Y', strtotime($month));
} else {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
    $title = "Last 30 Days Report";
}

// Fetch attendance data
$stmt = $conn->prepare("SELECT date, start_time, break_start_time, break_end_time, stop_time 
                       FROM attendance 
                       WHERE employee_id = ? 
                       AND date BETWEEN ? AND ?
                       ORDER BY date DESC");
$stmt->bind_param("iss", $employee_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$attendance_data = $result->fetch_all(MYSQLI_ASSOC);

// Calculate summary statistics
$total_days = 0;
$present_days = 0;
$total_hours = 0;
$total_breaks = 0;

foreach ($attendance_data as $record) {
    $total_days++;
    if ($record['start_time']) $present_days++;
    
    if ($record['start_time'] && $record['stop_time']) {
        $start = strtotime($record['start_time']);
        $stop = strtotime($record['stop_time']);
        $work_seconds = $stop - $start;
        
        // Subtract break time if available
        if ($record['break_start_time'] && $record['break_end_time']) {
            $break_start = strtotime($record['break_start_time']);
            $break_end = strtotime($record['break_end_time']);
            $break_seconds = $break_end - $break_start;
            $work_seconds -= $break_seconds;
            $total_breaks += $break_seconds;
        }
        
        $total_hours += $work_seconds;
    }
}

$attendance_percentage = $total_days > 0 ? round(($present_days / $total_days) * 100, 2) : 0;
$avg_hours_per_day = $present_days > 0 ? gmdate("H:i", $total_hours / $present_days) : '00:00';
$total_work_hours = gmdate("H:i", $total_hours);
$total_break_hours = gmdate("H:i", $total_breaks);

function formatTime($datetime) {
    if (!$datetime) return '-';
    $date = new DateTime($datetime, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Asia/Kolkata'));
    return $date->format('H:i');
}

function formatDate($date) {
    return date('D, d M Y', strtotime($date));
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- style css -->
     <link rel="stylesheet" href="../assets/css/style.css">
    <!-- sidebar css -->
     <link rel="stylesheet" href="../assets/css/sidebar.css">
    <style>
        .main-content {
            padding: 20px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            border-left: 4px solid var(--primary);
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .table-custom {
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table-custom thead {
            background-color: var(--primary);
            color: white;
        }
        
        .badge-present {
            background-color: rgba(76, 201, 240, 0.2);
            color: var(--success);
        }
        
        .badge-absent {
            background-color: rgba(247, 37, 133, 0.2);
            color: var(--danger);
        }
        
        .badge-late {
            background-color: rgba(248, 150, 30, 0.2);
            color: var(--warning);
        }
        
        .filter-section {
            background-color: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include('../sidebar.php') ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="filter-section">
                <h4><i class="fas fa-filter me-2"></i> Filter Reports</h4>
                <form method="get" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" class="form-select">
                            <option value="monthly" <?= $report_type == 'monthly' ? 'selected' : '' ?>>Monthly</option>
                            <option value="last30" <?= $report_type == 'last30' ? 'selected' : '' ?>>Last 30 Days</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Month</label>
                        <input type="month" name="month" class="form-control" 
                               value="<?= $month ?>" <?= $report_type == 'last30' ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i> Generate Report
                        </button>
                    </div>
                </form>
            </div>

            <h2 class="mb-4"><i class="fas fa-chart-pie me-2"></i> <?= $title ?></h2>
            
            <!-- Summary Cards -->
            <div class="row">
                <div class="col-md-3">
                    <div class="dashboard-card stat-card">
                        <h6 class="text-muted">Total Days</h6>
                        <h3><?= $total_days ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card stat-card">
                        <h6 class="text-muted">Present Days</h6>
                        <h3><?= $present_days ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card stat-card">
                        <h6 class="text-muted">Attendance %</h6>
                        <h3><?= $attendance_percentage ?>%</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card stat-card">
                        <h6 class="text-muted">Avg Hours/Day</h6>
                        <h3><?= $avg_hours_per_day ?></h3>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h5 class="mb-4"><i class="fas fa-chart-pie me-2"></i> Attendance Distribution</h5>
                        <canvas id="attendanceChart" height="250"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h5 class="mb-4"><i class="fas fa-chart-line me-2"></i> Weekly Hours Trend</h5>
                        <canvas id="hoursChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Detailed Records -->
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4><i class="fas fa-table me-2"></i> Detailed Attendance Records</h4>
                    <div class="text-muted">
                        Showing <?= count($attendance_data) ?> records
                        <span class="ms-2">Total Work: <?= $total_work_hours ?></span>
                        <span class="ms-2">Total Breaks: <?= $total_break_hours ?></span>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-custom table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Start Time</th>
                                <th>Break Start</th>
                                <th>Break End</th>
                                <th>Stop Time</th>
                                <th>Work Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_data as $record): ?>
                            <tr>
                                <td><?= formatDate($record['date']) ?></td>
                                <td>
                                    <?php if ($record['start_time']): ?>
                                        <span class="badge badge-present">Present</span>
                                    <?php else: ?>
                                        <span class="badge badge-absent">Absent</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatTime($record['start_time']) ?></td>
                                <td><?= formatTime($record['break_start_time']) ?></td>
                                <td><?= formatTime($record['break_end_time']) ?></td>
                                <td><?= formatTime($record['stop_time']) ?></td>
                                <td>
                                    <?php 
                                    if ($record['start_time'] && $record['stop_time']) {
                                        $start = strtotime($record['start_time']);
                                        $stop = strtotime($record['stop_time']);
                                        $work_seconds = $stop - $start;
                                        
                                        if ($record['break_start_time'] && $record['break_end_time']) {
                                            $break_start = strtotime($record['break_start_time']);
                                            $break_end = strtotime($record['break_end_time']);
                                            $work_seconds -= ($break_end - $break_start);
                                        }
                                        
                                        echo gmdate("H:i", $work_seconds);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Attendance Distribution Chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(attendanceCtx, {
            type: 'pie',
            data: {
                labels: ['Present', 'Absent'],
                datasets: [{
                    data: [<?= $present_days ?>, <?= $total_days - $present_days ?>],
                    backgroundColor: ['#4cc9f0', 'purple'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Weekly Hours Trend Chart (sample data - would need actual weekly data)
        const hoursCtx = document.getElementById('hoursChart').getContext('2d');
        const hoursChart = new Chart(hoursCtx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Work Hours',
                    data: [40, 38, 42, 35],
                    borderColor: '#019FE2',
                    backgroundColor: 'rgba(1, 159, 226, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Hours'
                        }
                    }
                }
            }
        });
        
        // Enable/disable month picker based on report type
        document.querySelector('select[name="report_type"]').addEventListener('change', function() {
            document.querySelector('input[name="month"]').disabled = this.value !== 'monthly';
        });
    </script>
</body>
</html>