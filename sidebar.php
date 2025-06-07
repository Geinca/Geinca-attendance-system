<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
            <i class="fas fa-clock"></i>
            <span>Dashboard</span>
        </a>

        <?php if ($_SESSION['role'] === 'admin'): ?>
            <!-- Admin-only Links -->
            <a href="manage_employees.php" class="nav-link">
                <i class="fas fa-users-cog"></i>
                <span>Manage Employees</span>
            </a>
            <a href="leave_management.php" class="nav-link">
                <i class="fa-solid fa-pen-nib"></i>
                <span>Leave Management</span>
            </a>
            <a href="holiday_management.php" class="nav-link">
                <i class="fa-solid fa-pen-nib"></i>
                <span>Holiday Management</span>
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-chart-line"></i>
                <span>All Reports</span>
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-cogs"></i>
                <span>Settings</span>
            </a>
        <?php else: ?>
            <!-- Employee-only Links -->
            <a href="attendance.php" class="nav-link">
                <i class="fas fa-calendar-alt"></i>
                <span>Attendance</span>
            </a>
            <a href="holiday_calender.php" class="nav-link">
                <i class="fas fa-calendar-alt"></i>
                <span>Holiday Calendar</span>
            </a>
            <a href="leave.php" class="nav-link">
                <i class="fas fa-calendar-check"></i>
                <span>Apply for Leave</span>
            </a>
            <a href="team_leaves.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>
                <span>Team Leaves</span>
            </a>
            <a href="employee_profile.php" class="nav-link">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        <?php endif; ?>

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
