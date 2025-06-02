<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "attendance_system");
$employee_id = $_SESSION['employee_id'];

// Fetch employee name for sidebar
$stmt_name = $conn->prepare("SELECT username FROM employees WHERE id = ?");
$stmt_name->bind_param("i", $employee_id);
$stmt_name->execute();
$result_name = $stmt_name->get_result();
$employee = $result_name->fetch_assoc();
$employee_name = $employee['name'] ?? 'Employee';

// Get current month and year for default filter
$current_month = date('m');
$current_year = date('Y');

// Handle filter form submission
$month = $_GET['month'] ?? $current_month;
$year = $_GET['year'] ?? $current_year;

// Validate month and year
$month = max(1, min(12, (int)$month));
$year = max(2000, min(2100, (int)$year));

// Fetch attendance records for the selected month/year
$stmt = $conn->prepare("SELECT date, start_time, break_start_time, break_end_time, stop_time 
                        FROM attendance 
                        WHERE employee_id = ? 
                        AND MONTH(date) = ? 
                        AND YEAR(date) = ?
                        ORDER BY date DESC");
$stmt->bind_param("iii", $employee_id, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

// Function to calculate total work time
function getTotalWorkTime($start, $stop, $breakStart, $breakEnd) {
    if (!$start || !$stop) return 'N/A';

    $start_ts = strtotime($start);
    $stop_ts = strtotime($stop);

    $break_duration = 0;
    if ($breakStart && $breakEnd) {
        $break_duration = strtotime($breakEnd) - strtotime($breakStart);
        if ($break_duration < 0) $break_duration = 0;
    }

    $work_seconds = ($stop_ts - $start_ts) - $break_duration;
    if ($work_seconds < 0) $work_seconds = 0;

    return gmdate("H:i:s", $work_seconds);
}

// Function to format time
function formatTime($datetime) {
    return $datetime ? date('H:i', strtotime($datetime)) : '-';
}

// Calculate summary statistics
$total_days = 0;
$total_hours = 0;
$present_days = 0;
$absent_days = 0;

// Create array of all days in the month
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$all_days = range(1, $days_in_month);

// Process attendance data
$attendance_data = [];
while ($row = $result->fetch_assoc()) {
    $day = date('j', strtotime($row['date']));
    $attendance_data[$day] = $row;
    
    if ($row['start_time']) {
        $present_days++;
        $work_time = getTotalWorkTime($row['start_time'], $row['stop_time'], $row['break_start_time'], $row['break_end_time']);
        if ($work_time != 'N/A') {
            list($h, $m, $s) = explode(':', $work_time);
            $total_hours += $h + ($m / 60) + ($s / 3600);
        }
    }
}

$absent_days = $days_in_month - $present_days;
$avg_hours_per_day = $present_days > 0 ? $total_hours / $present_days : 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome@6.4.0/css/all.min.css">
    <!-- sidebar css -->
     <link rel="stylesheet" href="../assets/css/style.css">
    <!-- sidebar css -->
     <link rel="stylesheet" href="../assets/css/sidebar.css">
    
    <style>
        
        /* Dashboard Styles */
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(1, 158, 226, 0.14);
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .summary-card {
            text-align: center;
            padding: 20px;
            border-radius: 12px;
            color: white;
            margin-bottom: 20px;
        }
        
        .summary-card .number {
            font-size: 2.5rem;
            font-weight: 600;
            margin: 10px 0;
        }
        
        .summary-card .label {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .summary-present {
            background: linear-gradient(135deg, var(--success), #2a9d8f);
        }
        
        .summary-absent {
            background: linear-gradient(135deg, var(--danger), #e63946);
        }
        
        .summary-hours {
            background: linear-gradient(135deg, var(--info), #4361ee);
        }
        
        .summary-avg {
            background: linear-gradient(135deg, var(--warning), #f3722c);
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
        
        .status-present {
            color: var(--success);
            font-weight: 500;
        }
        
        .status-absent {
            color: var(--danger);
            font-weight: 500;
        }
        
        .status-partial {
            color: var(--warning);
            font-weight: 500;
        }
        
        .badge-present {
            background-color: rgba(76, 201, 240, 0.2);
            color: var(--success);
        }
        
        .badge-absent {
            background-color: rgba(247, 37, 133, 0.2);
            color: var(--danger);
        }
        
        .badge-partial {
            background-color: rgba(248, 150, 30, 0.2);
            color: var(--warning);
        }
        
        .filter-form {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include('../sidebar.php') ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container py-3">
            <h2 class="mb-4"><i class="fas fa-calendar-alt me-2"></i>Attendance Records</h2>
            
            <!-- Filter Form -->
            <div class="filter-form mb-4">
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <label for="month" class="form-label">Month</label>
                        <select class="form-select" id="month" name="month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="year" class="form-label">Year</label>
                        <select class="form-select" id="year" name="year">
                            <?php for ($y = date('Y'); $y >= 2000; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="summary-card summary-present">
                        <i class="fas fa-calendar-check fa-2x"></i>
                        <div class="number"><?php echo $present_days; ?></div>
                        <div class="label">Present Days</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card summary-absent">
                        <i class="fas fa-calendar-times fa-2x"></i>
                        <div class="number"><?php echo $absent_days; ?></div>
                        <div class="label">Absent Days</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card summary-hours">
                        <i class="fas fa-clock fa-2x"></i>
                        <div class="number"><?php echo number_format($total_hours, 1); ?></div>
                        <div class="label">Total Hours</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card summary-avg">
                        <i class="fas fa-chart-line fa-2x"></i>
                        <div class="number"><?php echo number_format($avg_hours_per_day, 1); ?></div>
                        <div class="label">Avg Hours/Day</div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Calendar -->
            <div class="dashboard-card p-4 mb-4">
                <h5 class="mb-4"><i class="fas fa-calendar-day me-2"></i>Monthly Overview</h5>
                <div class="table-responsive">
                    <table class="table table-bordered text-center">
                        <thead>
                            <tr>
                                <th>Sun</th>
                                <th>Mon</th>
                                <th>Tue</th>
                                <th>Wed</th>
                                <th>Thu</th>
                                <th>Fri</th>
                                <th>Sat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Get first day of the month and its weekday (0=Sun, 6=Sat)
                            $first_day = date('w', strtotime("$year-$month-01"));
                            $day_count = 1;
                            $total_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                            
                            // Create calendar rows
                            for ($i = 0; $i < 6; $i++) {
                                echo '<tr>';
                                
                                // Create 7 columns for each week
                                for ($j = 0; $j < 7; $j++) {
                                    if (($i === 0 && $j < $first_day) || ($day_count > $total_days)) {
                                        // Empty cell before first day or after last day
                                        echo '<td style="height: 60px; background: #f8f9fa;"></td>';
                                    } else {
                                        $date_str = "$year-$month-" . str_pad($day_count, 2, '0', STR_PAD_LEFT);
                                        $attendance = $attendance_data[$day_count] ?? null;
                                        $status = '';
                                        
                                        if ($attendance && $attendance['start_time']) {
                                            if ($attendance['stop_time']) {
                                                $status = '<span class="badge badge-present">Present</span>';
                                            } else {
                                                $status = '<span class="badge badge-partial">Partial</span>';
                                            }
                                        } else {
                                            // Check if it's a future date
                                            if (strtotime($date_str) > time()) {
                                                $status = '<span class="text-muted">-</span>';
                                            } else {
                                                $status = '<span class="badge badge-absent">Absent</span>';
                                            }
                                        }
                                        
                                        echo '<td style="height: 60px;">';
                                        echo '<div>' . $day_count . '</div>';
                                        echo '<div style="font-size: 0.8rem;">' . $status . '</div>';
                                        echo '</td>';
                                        $day_count++;
                                    }
                                }
                                
                                echo '</tr>';
                                
                                // Stop creating rows if we've shown all days
                                if ($day_count > $total_days) {
                                    break;
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Detailed Attendance Table -->
            <div class="dashboard-card p-4">
                <h5 class="mb-4"><i class="fas fa-table me-2"></i>Detailed Records</h5>
                <div class="table-responsive">
                    <table class="table table-custom table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Status</th>
                                <th>Start Time</th>
                                <th>Break Start</th>
                                <th>Break End</th>
                                <th>Stop Time</th>
                                <th>Total Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Reset result pointer
                            $result->data_seek(0);
                            
                            while ($row = $result->fetch_assoc()): 
                                $date = new DateTime($row['date']);
                                $day_name = $date->format('l');
                                $is_weekend = in_array($day_name, ['Saturday', 'Sunday']);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['date']); ?></td>
                                <td><?php echo $day_name; ?></td>
                                <td>
                                    <?php if ($row['start_time']): ?>
                                        <?php if ($row['stop_time']): ?>
                                            <span class="status-present">Present</span>
                                        <?php else: ?>
                                            <span class="status-partial">Partial</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="status-absent">Absent</span>
                                    <?php endif; ?>
                                </td>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('sidebar-active');
        });

        // Toggle sidebar collapse on desktop
        let isCollapsed = false;
        
        function toggleSidebarCollapse() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            isCollapsed = !isCollapsed;
            sidebar.classList.toggle('sidebar-collapsed');
            mainContent.classList.toggle('main-content-expanded');
            
            // Store preference in localStorage
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        }
        
        // Check for saved preference
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            toggleSidebarCollapse();
        }
        
        // Add click event to all nav-links to close sidebar on mobile after click
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    document.getElementById('sidebar').classList.remove('sidebar-active');
                }
            });
        });
    </script>
</body>
</html>