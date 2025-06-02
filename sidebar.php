<div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($employee_name); ?>&background=random" alt="Profile">
            <h3><?php echo !empty($employee_name) ? htmlspecialchars($employee_name) : "Employee"; ?></h3>
        </div>
        <nav class="mt-3">
            <a href="./dashboard.php" class="nav-link active">
                <i class="fas fa-clock"></i>
                <span>Time Tracker</span>
            </a>
            <a href="./attendance.php" class="nav-link">
                <i class="fas fa-calendar-alt"></i>
                <span>Attendance</span>
            </a>
            <a href="./holiday_calender.php" class="nav-link">
                <i class="fas fa-calendar-alt"></i>
                <span>Holiday Calender</span>
            </a>
            <a href="./employee_reports.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="./employee_profile.php" class="nav-link">
                <i class="fas fa-user"></i>
                <span>Profile</span>
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