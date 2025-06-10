<?php
include 'db_config.php';

// Fetch employee name for sidebar
$stmt_name = $conn->prepare("SELECT name FROM employees WHERE id = ?");
$stmt_name->bind_param("i", $employee_id);
$stmt_name->execute();
$result_name = $stmt_name->get_result();
$employee = $result_name->fetch_assoc();
$employee_name = $employee['name'] ?? 'Employee'; //

?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($employee_name); ?>&background=random" alt="Profile">
        <h3><?php echo !empty($employee_name) ? htmlspecialchars($employee_name) : "Employee"; ?></h3>
    </div>
    <nav class="mt-3">
        <a href="dashboard.php" class="nav-link active">
            <!-- <i class="fas fa-clock"></i> -->
            <i class="ri-dashboard-line"></i>
            <span>Dashboard</span>
        </a>

        <?php if ($_SESSION['role'] === 'admin'): ?>
            <!-- Admin-only Links -->
            <a href="manage_employees.php" class="nav-link">
                <i class="ri-group-line"></i>
                <span>Manage Employees</span>
            </a>
            <a href="leave_management.php" class="nav-link">
                <i class="ri-pencil-line"></i>
                <span>Leave Management</span>
            </a>
            <a href="salary_management.php" class="nav-link">
    
                <i class="ri-wallet-line"></i>
                <span>Salary Management</span>
            </a>
            <a href="holiday_management.php" class="nav-link">
                <i class="ri-ball-pen-line"></i>
                <span>Holiday Management</span>
            </a>
        <?php else: ?>
            <!-- Employee-only Links -->
            <a href="attendance.php" class="nav-link">
                <i class="ri-book-read-line"></i>
                <span>Attendance</span>
            </a>
            <a href="holiday_calender.php" class="nav-link">
                <i class="ri-calendar-event-line"></i>
                <span>Holiday Calendar</span>
            </a>
            <a href="leave.php" class="nav-link">
                <i class="ri-calendar-check-line"></i>
                <span>Apply for Leave</span>
            </a>
            <a href="team_leaves.php" class="nav-link">
                <i class="ri-team-line"></i>
                <span>Team Leaves</span>
            </a>
            <a href="employee_profile.php" class="nav-link">
                <i class="ri-file-user-line"></i>
                <span>Profile</span>
            </a>
        <?php endif; ?>

        <div class="mt-4 pt-3 border-top border-light mx-3"></div>
        <a href="logout.php" class="nav-link">
            <i class="ri-logout-box-r-line"></i>
            <span>Logout</span>
        </a>
    </nav>
</div>

<!-- Toggle Sidebar Button (visible on mobile) -->
<button class="toggle-sidebar d-lg-none" id="toggleSidebar">
    <i class="fas fa-bars"></i>
</button>