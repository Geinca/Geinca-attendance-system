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

// fetch total employee count expect admin
$total_employees = 0;

if ($role === 'admin') {
    $stmt_emp_count = $conn->prepare("SELECT COUNT(*) AS total FROM employees WHERE role != 'admin'");
    $stmt_emp_count->execute();
    $result_emp_count = $stmt_emp_count->get_result();
    $row_emp_count = $result_emp_count->fetch_assoc();
    $total_employees = $row_emp_count['total'] ?? 0;
}

// fetch how many employees are present
$present_today = 0;

if ($role === 'admin') {
    // First, get IDs of all non-admin employees
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total 
        FROM attendance 
        WHERE DATE(date) = CURDATE() 
        AND employee_id IN (
            SELECT id FROM employees WHERE role != 'admin'
        )
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $present_today = $row['total'] ?? 0;
}

// fetch how many employees are on leave
$on_leave_today = 0;

if ($role === 'admin') {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM leave_applications
        WHERE CURDATE() BETWEEN start_date AND end_date
        AND employee_id IN (
            SELECT id FROM employees WHERE role != 'admin'
        )
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $on_leave_today = $row['total'] ?? 0;
}

// fetch pending leave requests
$pending_requests = 0;

if ($role === 'admin') {
    // Count leave requests with status = 'pending' where the requester is NOT an admin
    $stmtPending = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM leave_applications AS l
        JOIN employees          AS e ON e.id = l.employee_id
        WHERE l.status = 'pending'      -- adjust if your column is named differently
          AND e.role   != 'admin'
    ");
    $stmtPending->execute();
    $resultPending = $stmtPending->get_result();
    $rowPending    = $resultPending->fetch_assoc();
    $pending_requests = $rowPending['total'] ?? 0;
}




// Simple version without report type
$start_date = date('Y-m-d', strtotime('-30 days'));
$end_date = date('Y-m-d');
$title = "Last 30 Days Report";

// Fetch attendance data of one employee
$stmt = $conn->prepare("SELECT date, start_time, break_start_time, break_end_time, stop_time 
                       FROM attendance 
                       WHERE employee_id = ? 
                       AND date BETWEEN ? AND ?
                       ORDER BY date DESC");
$stmt->bind_param("iss", $employee_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$attendance_data = $result->fetch_all(MYSQLI_ASSOC);

// For paginated today's attendance of all non-admins
$limit = 10; // records per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;
$today = date('Y-m-d');
$total_stmt = $conn->prepare("
    SELECT COUNT(*) AS total 
    FROM attendance a 
    JOIN employees e ON a.employee_id = e.id 
    WHERE e.role != 'admin' AND a.date = ?
");
$total_stmt->bind_param("s", $today);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_records = $total_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_records / $limit);
$today_attendance_stmt = $conn->prepare("
    SELECT 
        a.date, a.start_time, a.stop_time, a.break_start_time, a.break_end_time, 
        e.username 
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    WHERE e.role != 'admin' AND a.date = ?
    ORDER BY a.start_time DESC
    LIMIT ? OFFSET ?
");
$today_attendance_stmt->bind_param("sii", $today, $limit, $offset);
$today_attendance_stmt->execute();
$result_all = $today_attendance_stmt->get_result();



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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .pagination .page-link {
            color: #007bff;
        }

        .pagination .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
            color: #fff;
        }
    </style>
</head>

<body>
    <!-- Modern Sidebar -->
    <?php include('sidebar.php') ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">

        <div class="container py-3">
            <div class="dashboard-card p-4 mb-5">
                <div class="mb-4">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div id="live-clock" class="text-muted" style="font-size: 1rem; font-weight: 600;">
                            <?php echo ('<i class="fas fa-clock"></i> ' . date('l, F j, Y h:i:s A')); ?>
                        </div>
                        <div class="status-badge <?php echo (!$attendance || $attendance['stop_time']) ? 'badge-inactive' : ($attendance['break_start_time'] && (!$attendance['break_end_time'] || strtotime($attendance['break_end_time']) < strtotime($attendance['break_start_time'])) ? 'badge-break' : 'badge-active'); ?>">
                            <?php echo (!$attendance || $attendance['stop_time']) ? 'Offline' : ($attendance['break_start_time'] && (!$attendance['break_end_time'] || strtotime($attendance['break_end_time']) < strtotime($attendance['break_start_time'])) ? 'On Break' : 'Active'); ?>
                        </div>
                    </div>

                    <h4 class="mb-0">Dashboard overview</h4>

                </div>

                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="dashboard-card stat-card">
                            <h6 class="text-muted"><?= ($role === 'admin') ? 'Total Employees' : 'Total Days' ?></h6>
                            <h3><?= ($role === 'admin') ? $total_employees : $total_days ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card stat-card">
                            <h6 class="text-muted"><?= ($role === 'admin') ? 'Present Today' : 'Present Days' ?></h6>
                            <h3><?= ($role === 'admin') ? $present_today : $present_days ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card stat-card">
                            <h6 class="text-muted"><?= ($role === 'admin') ? 'On Leave' : 'Attendance %' ?></h6>
                            <h3><?= ($role === 'admin') ? $on_leave_today : $attendance_percentage . '%' ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card stat-card">
                            <h6 class="text-muted"><?= ($role === 'admin') ? 'Pending Requests' : 'Avg Hours/Days' ?></h6>
                            <h3><?= ($role === 'admin') ? $pending_requests : $avg_hours_per_day ?></h3>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <?php if ($role === 'admin'): ?>
                        <div class="col-md-12">
                            <div class="dashboard-card p-4 h-100">
                                <h5 class="mb-4"><i class="fas fa-calendar-alt me-2"></i>Today's Activity (All Employees)</h5>
                                <div class="table-responsive">
                                    <table class="table table-custom table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Total Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($result_all->num_rows > 0): ?>
                                                <?php while ($row = $result_all->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['username']) ?></td>
                                                        <td><?= htmlspecialchars($row['date']) ?></td>
                                                        <td>
                                                            <?php if ($row['stop_time']): ?>
                                                                <span class="badge bg-danger bg-opacity-10 text-danger">Completed</span>
                                                            <?php elseif ($row['break_start_time'] && (!$row['break_end_time'] || strtotime($row['break_end_time']) < strtotime($row['break_start_time']))): ?>
                                                                <span class="badge bg-warning bg-opacity-10 text-warning">On Break</span>
                                                            <?php elseif ($row['start_time']): ?>
                                                                <span class="badge bg-success bg-opacity-10 text-success">Working</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary bg-opacity-10 text-muted">No Activity</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= getTotalWorkTime($row['start_time'], $row['stop_time'], $row['break_start_time'], $row['break_end_time']) ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4">No attendance records found for today.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <nav class="mt-3">
                                    <ul class="pagination justify-content-center">
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($role !== 'admin'): ?>
                        <div class="col-md-6 mb-4">
                            <div class="dashboard-card p-4 h-100">
                                <h5 class="mb-4"><i class="fas fa-clock me-2"></i><?= ($role === 'admin') ? "Attendance Overview" : "Today's Status" ?></h5>
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
                    <?php endif; ?>

                    <?php if ($role == 'admin'): ?>
                    <?php else: ?>
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
                    <?php endif; ?>
                </div>
            </div>
        </div>

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
    <!-- <script src="../assets/js/chatbot.js"></script> -->
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