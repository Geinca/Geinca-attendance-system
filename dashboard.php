<?php
date_default_timezone_set('Asia/Kolkata');
session_start();


if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "attendance_system");
$employee_id = $_SESSION['employee_id'];

// Get role for logged in user
$stmt = $conn->prepare("SELECT username, role FROM employees WHERE id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$username = $user['username'];
$role = $user['role'];
$department = $user['department'] ?? '';


$all_employees = [];
if ($role === 'admin') {
    $stmt_all_emp = $conn->prepare("SELECT * FROM employees ORDER BY id ASC");
    $stmt_all_emp->execute();
    $result_all_emp = $stmt_all_emp->get_result();
    $all_employees = $result_all_emp->fetch_all(MYSQLI_ASSOC);
}

$today = date('Y-m-d');

// Fetch today's attendance record for employee
$stmt = $conn->prepare("SELECT id, start_time, break_start_time, break_end_time, stop_time FROM attendance WHERE employee_id = ? AND date = ?");
$stmt->bind_param("is", $employee_id, $today);
$stmt->execute();
$result = $stmt->get_result();
$attendance = $result->fetch_assoc();

function formatTime($datetime)
{
    if (!$datetime) return 'Not set';

    $date = new DateTime($datetime);  // Already in IST
    return $date->format('h:i:s a');
}

function getTotalWorkTime($start, $stop, $breakStart, $breakEnd)
{
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

// Fetch employee details
$stmt_name = $conn->prepare("SELECT username FROM employees WHERE id = ?");
$stmt_name->bind_param("i", $employee_id);
$stmt_name->execute();
$result_name = $stmt_name->get_result();
$employee = $result_name->fetch_assoc();
$employee_name = $employee['username'] ?? 'Employee';
$department = $employee['department'] ?? '';


// Simple version without report type
$start_date = date('Y-m-d', strtotime('-30 days'));
$end_date = date('Y-m-d');
$title = "Last 30 Days Report";

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
?>

<!DOCTYPE html>
<html>

<head>
    <title>Employee Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/employee_dashboard.css">
    <link rel="stylesheet" href="assets/css/chatbot.css">

    <style>
        /* only foy admin table */
        table {
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }

        table thead th {
            background-color: #343a40;
            color: #fff;
            border: none;
        }

        table tbody tr {
            background-color: #ffffff;
            transition: box-shadow 0.3s ease;
        }

        table tbody tr:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <!-- Modern Sidebar -->
    <?php include('sidebar.php') ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <?php if ($role === 'admin'): ?>
            <div class="container py-3">
                <h2>Admin Dashboard - Employee List</h2>
                <table class="table table-hover table-responsive shadow-sm rounded" id="employeeTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Employee ID</th>
                            <th>Username</th>
                            <th>Department</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_employees as $emp): ?>
                            <tr>
                                <td><?= htmlspecialchars($emp['id']) ?></td>
                                <td><?= htmlspecialchars($emp['username']) ?></td>
                                <td><?= htmlspecialchars($emp['department']) ?></td>
                                <td><?= htmlspecialchars(ucfirst($emp['role'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <div class="container py-3">
                <div class="dashboard-card p-4 mb-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="d-flex flex-column">
                            <h2 class="mb-0">
                                <i class="fas fa-user-clock me-2"></i>Employee Dashboard
                            </h2>
                            <div id="live-clock" class="text-muted" style="font-size: 1rem; font-weight: normal;">
                                <?php echo date('l, F j, Y h:i:s A'); ?>
                            </div>
                        </div>

                        <div class="status-badge <?php echo (!$attendance || $attendance['stop_time']) ? 'badge-inactive' : ($attendance['break_start_time'] && (!$attendance['break_end_time'] || strtotime($attendance['break_end_time']) < strtotime($attendance['break_start_time'])) ? 'badge-break' : 'badge-active'); ?>">
                            <?php echo (!$attendance || $attendance['stop_time']) ? 'Offline' : ($attendance['break_start_time'] && (!$attendance['break_end_time'] || strtotime($attendance['break_end_time']) < strtotime($attendance['break_start_time'])) ? 'On Break' : 'Active'); ?>
                        </div>
                    </div>

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
            </div>
        <?php endif; ?>
    </div>

    <!-- Chatbot Widget -->
    <!-- <div id="chatbot-widget" class="chatbot-widget collapsed">
        <div class="chatbot-header">
            <span><i class="fas fa-robot me-2"></i>Employee Support</span>
            <button id="chatbot-toggle" class="chatbot-toggle">
                <i class="fas fa-minus"></i>
            </button>
        </div>
        <div id="chatbot-messages" class="chatbot-messages">
            <div class="text-center py-3 text-muted">
                Loading chat history...
            </div>
        </div>
        <div id="chatbot-typing-indicator" class="typing-indicator">
            <i class="fas fa-ellipsis-h"></i> Bot is typing...
        </div>
        <div class="chatbot-input">
            <input type="text" id="chatbot-message-input" placeholder="Type your message..." autocomplete="off">
            <button id="chatbot-send-button">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div> -->

    <!-- JavaScript -->
    <script src="../assets/js/chatbot.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>

    <script>
        // Toggle sidebar on mobile
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');

            sidebar.classList.toggle('sidebar-collapsed');
            mainContent.classList.toggle('main-content-expanded');

            // Rotate the icon
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('sidebar-collapsed')) {
                icon.classList.remove('fa-chevron-left');
                icon.classList.add('fa-chevron-right');
            } else {
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-left');
            }

            // Store preference in localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('sidebar-collapsed'));
        });

        // Check for saved preference on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.getElementById('mainContent');
                const toggleBtn = document.getElementById('toggleSidebar');
                const icon = toggleBtn.querySelector('i');

                sidebar.classList.add('sidebar-collapsed');
                mainContent.classList.add('main-content-expanded');
                icon.classList.remove('fa-chevron-left');
                icon.classList.add('fa-chevron-right');
            }
        });

        // Add click event to all nav-links to close sidebar on mobile after click
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    document.getElementById('sidebar').classList.remove('sidebar-active');
                }
            });
        });

        // Real-time clock
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

        // Update clock every second
        setInterval(updateClock, 1000);
        updateClock(); // Initialize immediately
    </script>
</body>

</html>