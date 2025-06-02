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

function formatTime($datetime)
{
    if (!$datetime) return 'Not set';

    $date = new DateTime($datetime, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Asia/Kolkata'));
    return $date->format('H:i:s');
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
?>

<!DOCTYPE html>
<html>

<head>
    <title>Employee Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- style css -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- sidebar css -->
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <!-- employee dashboard css -->
    <link rel="stylesheet" href="../assets/css/employee_dashboard.css">
    
</head>

<body>
    <!-- Sidebar -->
    <?php include('../sidebar.php') ?>

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