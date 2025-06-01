<?php
// Set default timezone to Indian Standard Time
date_default_timezone_set('Asia/Kolkata');
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "attendance_system");
$employee_id = $_SESSION['employee_id'];
$today = date('Y-m-d');

// Fetch employee name for sidebar
$stmt_name = $conn->prepare("SELECT username FROM employees WHERE id = ?");
$stmt_name->bind_param("i", $employee_id);
$stmt_name->execute();
$result_name = $stmt_name->get_result();
$employee = $result_name->fetch_assoc();
$employee_name = $employee['username'] ?? 'Employee'; // Changed from 'name' to 'username'

// Fetch today's attendance record for employee
$stmt = $conn->prepare("SELECT id, start_time, break_start_time, break_end_time, stop_time FROM attendance WHERE employee_id = ? AND date = ?");
$stmt->bind_param("is", $employee_id, $today);
$stmt->execute();
$result = $stmt->get_result();
$attendance = $result->fetch_assoc();

function formatTime($datetime) {
    if (!$datetime) return 'Not set';
    
    $date = new DateTime($datetime, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Asia/Kolkata'));
    return $date->format('H:i:s');
}

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

    $hours = floor($work_seconds / 3600);
    $minutes = floor(($work_seconds % 3600) / 60);
    $seconds = $work_seconds % 60;
    
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
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
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
        }
        
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary) 0%, #0066cc 100%);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-collapsed {
            width: var(--sidebar-collapsed-width);
        }
        
        .sidebar-collapsed .sidebar-header h3,
        .sidebar-collapsed .nav-link span {
            display: none;
        }
        
        .sidebar-collapsed .nav-link {
            justify-content: center;
        }
        
        .sidebar-collapsed .nav-link i {
            margin-right: 0;
            font-size: 1.2rem;
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h3 {
            margin-bottom: 0;
            font-weight: 600;
        }
        
        .sidebar-header img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
            border: 3px solid rgba(255, 255, 255, 0.2);
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 15px 20px;
            margin: 5px 10px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
        }
        
        .nav-link i {
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            transition: all 0.3s ease;
            padding: 20px;
        }
        
        .main-content-expanded {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        .toggle-sidebar {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 1001;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        
        /* Dashboard Styles */
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
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar-active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .toggle-sidebar {
                display: flex;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($employee_name); ?>&background=random" alt="Profile">
            <h3><?php echo htmlspecialchars($employee_name); ?></h3>
        </div>
        <nav class="mt-3">
            <a href="dashboard.php" class="nav-link active">
                <i class="fas fa-clock"></i>
                <span>Time Tracker</span>
            </a>
            <a href="attendance.php" class="nav-link">
                <i class="fas fa-calendar-alt"></i>
                <span>Attendance</span>
            </a>
            <a href="profile.php" class="nav-link">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="reports.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <div class="mt-4 pt-3 border-top border-light mx-3"></div>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>

    <!-- Toggle Sidebar Button (visible on mobile) -->
    <button class="toggle-sidebar d-lg-none" id="toggleSidebar">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container py-3">
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