<?php
session_start();
if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "attendance_system");
$employee_id = $_SESSION['employee_id'];

// Get logged-in user's username to check admin
$stmt = $conn->prepare("SELECT username FROM employees WHERE id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$stmt->bind_result($logged_in_username);
$stmt->fetch();
$stmt->close();



// Helper functions
function formatTime($datetime) {
    return $datetime ? date('H:i:s', strtotime($datetime)) : 'Not set';
}

function getTotalWorkTimeSeconds($start, $stop, $breakStart, $breakEnd) {
    if (!$start || !$stop) return 0;
    $start_ts = strtotime($start);
    $stop_ts = strtotime($stop);
    $break_duration = 0;
    if ($breakStart && $breakEnd) {
        $break_duration = strtotime($breakEnd) - strtotime($breakStart);
        if ($break_duration < 0) $break_duration = 0;
    }
    $work_seconds = ($stop_ts - $start_ts) - $break_duration;
    return max(0, $work_seconds);
}

function secondsToHoursMinutes($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    return sprintf("%02d:%02d", $hours, $minutes);
}

// Get filter inputs
$filter_username = isset($_GET['username']) ? $_GET['username'] : '';
$filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Fetch all employees for the dropdown filter
$employees_res = $conn->query("SELECT id, username FROM employees ORDER BY username ASC");

// Prepare filtered attendance data
$employee_data = [];

$whereClauses = [];
$params = [];
$types = "";

// Filter by username
if ($filter_username) {
    $stmt_user = $conn->prepare("SELECT id FROM employees WHERE username = ?");
    $stmt_user->bind_param("s", $filter_username);
    $stmt_user->execute();
    $stmt_user->bind_result($filter_employee_id);
    $stmt_user->fetch();
    $stmt_user->close();

    if ($filter_employee_id) {
        $whereClauses[] = "employee_id = ?";
        $params[] = $filter_employee_id;
        $types .= "i";
    } else {
        // No such user, no records to show
        $employee_data = [];
    }
}

// Filter by date range
if ($filter_start_date && $filter_end_date) {
    $whereClauses[] = "date BETWEEN ? AND ?";
    $params[] = $filter_start_date;
    $params[] = $filter_end_date;
    $types .= "ss";
} elseif ($filter_start_date) {
    $whereClauses[] = "date >= ?";
    $params[] = $filter_start_date;
    $types .= "s";
} elseif ($filter_end_date) {
    $whereClauses[] = "date <= ?";
    $params[] = $filter_end_date;
    $types .= "s";
}

// If no username filter, fetch all employees, else just filtered employee(s)
$employee_ids = [];

if (!$filter_username) {
    while ($emp = $employees_res->fetch_assoc()) {
        $employee_ids[] = $emp['id'];
    }
} elseif (isset($filter_employee_id)) {
    $employee_ids[] = $filter_employee_id;
}

if (count($employee_ids) > 0) {
    foreach ($employee_ids as $emp_id) {
        // Build attendance where clause
        $attWhere = "employee_id = $emp_id";
        if ($filter_start_date && $filter_end_date) {
            $attWhere .= " AND date BETWEEN '$filter_start_date' AND '$filter_end_date'";
        } elseif ($filter_start_date) {
            $attWhere .= " AND date >= '$filter_start_date'";
        } elseif ($filter_end_date) {
            $attWhere .= " AND date <= '$filter_end_date'";
        }

        // Fetch attendance records for this employee with filter
        $sql = "SELECT date, start_time, break_start_time, break_end_time, stop_time FROM attendance WHERE $attWhere ORDER BY date DESC";

        $result = $conn->query($sql);

        $weekly_seconds = 0;
        $monthly_seconds = 0;
        $last_attendance = null;

        // Calculate weekly and monthly work time for the filtered range or defaults
        while ($row = $result->fetch_assoc()) {
            // For last attendance
            if (!$last_attendance) $last_attendance = $row;

            // Weekly and monthly sums (if no date filter, consider last 7 days and this month)
            $record_date = $row['date'];
            $work_seconds = getTotalWorkTimeSeconds($row['start_time'], $row['stop_time'], $row['break_start_time'], $row['break_end_time']);

            // Weekly
            if (!$filter_start_date && !$filter_end_date) {
                // If no filter dates, calculate weekly (last 7 days) and monthly (current month)
                if (strtotime($record_date) >= strtotime("-7 days")) {
                    $weekly_seconds += $work_seconds;
                }
                if (date('Y-m', strtotime($record_date)) == date('Y-m')) {
                    $monthly_seconds += $work_seconds;
                }
            } else {
                // If date filter exists, sum all filtered
                $weekly_seconds += $work_seconds;
                $monthly_seconds += $work_seconds;
            }
        }

        $username = '';
        // Get username for emp_id
        $stmt_un = $conn->prepare("SELECT username FROM employees WHERE id = ?");
        $stmt_un->bind_param("i", $emp_id);
        $stmt_un->execute();
        $stmt_un->bind_result($username);
        $stmt_un->fetch();
        $stmt_un->close();

        $employee_data[] = [
            'username' => $username,
            'last_attendance' => $last_attendance,
            'weekly_hours' => secondsToHoursMinutes($weekly_seconds),
            'monthly_hours' => secondsToHoursMinutes($monthly_seconds),
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 80px;
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --light-bg: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
            background-color: #f5f5f5;
        }
        
        /* Sidebar Styles */
        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: var(--secondary-color);
            color: white;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        #sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }
        
        .sidebar-header {
            padding: 20px;
            background: rgba(0,0,0,0.2);
            text-align: center;
        }
        
        .sidebar-header h3 {
            margin: 0;
            white-space: nowrap;
        }
        
        .sidebar-header .logo-collapsed {
            display: none;
        }
        
        #sidebar.collapsed .logo-full {
            display: none;
        }
        
        #sidebar.collapsed .logo-collapsed {
            display: block;
        }
        
        #sidebar.collapsed .nav-link span {
            display: none;
        }
        
        #sidebar.collapsed .nav-link i {
            margin-right: 0;
            font-size: 1.3rem;
        }
        
        #sidebar.collapsed .dropdown-toggle::after {
            display: none;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 0;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content Styles */
        #main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s;
            padding: 20px;
        }
        
        #main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        .top-navbar {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        /* Cards */
        .dashboard-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px;
            }
            
            #sidebar.collapsed {
                margin-left: 0;
                width: 80px;
            }
            
            #main-content {
                margin-left: 0;
            }
            
            #main-content.expanded {
                margin-left: 0;
            }
            
            body.active #sidebar {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div id="sidebar">
        <div class="sidebar-header d-flex flex-column align-items-center justify-content-center">
            <div class="logo-full">
                <h3>Admin Panel</h3>
            </div>
            <div class="logo-collapsed">
                <i class="fas fa-user-shield fa-2x"></i>
            </div>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="employees.php">
                    <i class="fas fa-users"></i>
                    <span>Employees</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="attendance.php">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Attendance</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="leaves.php">
                    <i class="fas fa-calendar-minus"></i>
                    <span>Leave Management</span>
                </a>
            </li>
            
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="reportsDropdown" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <ul class="dropdown-menu" aria-labelledby="reportsDropdown">
                    <li><a class="dropdown-item" href="attendance-reports.php">Attendance Reports</a></li>
                    <li><a class="dropdown-item" href="leave-reports.php">Leave Reports</a></li>
                    <li><a class="dropdown-item" href="performance-reports.php">Performance Reports</a></li>
                </ul>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
        
        <div class="position-absolute bottom-0 start-0 p-3 w-100">
            <a href="logout.php" class="btn btn-outline-light w-100">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div id="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar d-flex justify-content-between align-items-center">
            <div>
                <button id="sidebarToggle" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <span id="live-clock" class="text-muted"></span>
                </div>
                <div class="dropdown">
                    <button class="btn btn-link dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        Admin User
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Dashboard Content -->
        <div class="container-fluid">
            <h4 class="mb-4">Dashboard Overview</h4>
            
            <!-- Stats Cards -->
            <div class="row">
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">Total Employees</h6>
                                <h3>124</h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="fas fa-users text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">Present Today</h6>
                                <h3>98</h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="fas fa-user-check text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">On Leave</h6>
                                <h3>12</h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="fas fa-calendar-minus text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">Pending Requests</h6>
                                <h3>5</h3>
                            </div>
                            <div class="bg-info bg-opacity-10 p-3 rounded">
                                <i class="fas fa-bell text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts and Tables -->
            <div class="row mt-4">
                <div class="col-md-8">
                    <div class="dashboard-card">
                        <h5 class="mb-3">Attendance Overview</h5>
                        <div id="attendanceChart" style="height: 300px;">
                            <!-- Chart would be rendered here -->
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-chart-line fa-3x mb-3"></i>
                                <p>Attendance chart will be displayed here</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="dashboard-card">
                        <h5 class="mb-3">Recent Activities</h5>
                        <div class="list-group">
                            <div class="list-group-item border-0">
                                <div class="d-flex">
                                    <div class="me-3">
                                        <i class="fas fa-user-clock text-primary"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted">10 min ago</small>
                                        <p class="mb-0">John Doe checked in</p>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item border-0">
                                <div class="d-flex">
                                    <div class="me-3">
                                        <i class="fas fa-calendar-minus text-warning"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted">25 min ago</small>
                                        <p class="mb-0">Jane Smith applied for leave</p>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item border-0">
                                <div class="d-flex">
                                    <div class="me-3">
                                        <i class="fas fa-user-plus text-success"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted">1 hour ago</small>
                                        <p class="mb-0">New employee registered</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <h5 class="mb-3">Pending Approvals</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>John Doe</td>
                                        <td>Leave Application</td>
                                        <td>2023-06-15 to 2023-06-17</td>
                                        <td><span class="badge bg-warning">Pending</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-success">Approve</button>
                                            <button class="btn btn-sm btn-danger ms-1">Reject</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Jane Smith</td>
                                        <td>Overtime Request</td>
                                        <td>2023-06-10</td>
                                        <td><span class="badge bg-warning">Pending</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-success">Approve</button>
                                            <button class="btn btn-sm btn-danger ms-1">Reject</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sidebar Toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('main-content').classList.toggle('expanded');
        });
        
        // Live Clock
        function updateClock() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            document.getElementById('live-clock').textContent = now.toLocaleDateString('en-US', options);
        }
        
        setInterval(updateClock, 1000);
        updateClock();
        
        // Initialize dropdowns
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl)
        });
    </script>
</body>
</html>