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

if ($logged_in_username !== "priyabrata7077@gmail.com") {
    // Not admin, deny access
    echo "Access denied. You do not have permission to view this page.";
    exit;
}

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
<html>
<head>
    <title>Admin Dashboard - Employee Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- css -->
     <link rel="stylesheet" href="admin_dashboard.css">
</head>
<body class="container mt-5">
    <h1>Admin Dashboard</h1>
    <p>Logged in as: <strong><?php echo htmlspecialchars($logged_in_username); ?></strong></p>
    <a href="logout.php" class="btn btn-danger mb-3">Logout</a>

    <form method="get" class="row g-3 mb-4">
        <div class="col-md-4">
            <label for="username" class="form-label">Filter by Username</label>
            <select name="username" id="username" class="form-select">
                <option value="">-- All Users --</option>
                <?php
                // Reset pointer and re-fetch all employees for dropdown
                $employees_res = $conn->query("SELECT username FROM employees ORDER BY username ASC");
                while ($row = $employees_res->fetch_assoc()):
                ?>
                    <option value="<?php echo htmlspecialchars($row['username']); ?>"
                        <?php if ($filter_username === $row['username']) echo "selected"; ?>>
                        <?php echo htmlspecialchars($row['username']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label for="start_date" class="form-label">Start Date</label>
            <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($filter_start_date); ?>">
        </div>

        <div class="col-md-3">
            <label for="end_date" class="form-label">End Date</label>
            <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($filter_end_date); ?>">
        </div>

        <div class="col-md-2 align-self-end">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </form>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Last Attendance Date</th>
                <th>Start Time</th>
                <th>Break Start</th>
                <th>Break End</th>
                <th>Stop Time</th>
                <th>Weekly Work Hours</th>
                <th>Monthly Work Hours</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employee_data as $emp):
                $att = $emp['last_attendance'];
            ?>
            <tr>
                <td><?php echo htmlspecialchars($emp['username']); ?></td>
                <td><?php echo $att ? htmlspecialchars($att['date']) : 'No records'; ?></td>
                <td><?php echo $att ? formatTime($att['start_time']) : '-'; ?></td>
                <td><?php echo $att ? formatTime($att['break_start_time']) : '-'; ?></td>
                <td><?php echo $att ? formatTime($att['break_end_time']) : '-'; ?></td>
<td><?php echo $att ? formatTime($att['stop_time']) : '-'; ?></td>
<td><?php echo $emp['weekly_hours']; ?></td>
<td><?php echo $emp['monthly_hours']; ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($employee_data)): ?>
<tr>
<td colspan="8" class="text-center">No records found</td>
</tr>
<?php endif; ?>
</tbody>
</table>

</body> </html> 