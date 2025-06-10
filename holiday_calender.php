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
$employee_name = $employee['username'] ?? 'Employee';

// Handle form submission to add new holiday
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_holiday'])) {
    $holiday_name = $_POST['holiday_name'];
    $holiday_date = $_POST['holiday_date'];
    $holiday_type = $_POST['holiday_type'];

    $insert_stmt = $conn->prepare("INSERT INTO holidays (name, date, type) VALUES (?, ?, ?)");
    $insert_stmt->bind_param("sss", $holiday_name, $holiday_date, $holiday_type);
    $insert_stmt->execute();
}

// Handle holiday deletion
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $delete_stmt = $conn->prepare("DELETE FROM holidays WHERE id = ?");
    $delete_stmt->bind_param("i", $delete_id);
    $delete_stmt->execute();
}

// Get current year and month
$current_year = date('Y');
$current_month = date('m');
$year = isset($_GET['year']) ? $_GET['year'] : $current_year;
$month = isset($_GET['month']) ? $_GET['month'] : $current_month;

// Fetch holidays for the selected year
$holidays_stmt = $conn->prepare("SELECT id, name, date, type FROM holidays WHERE YEAR(date) = ? ORDER BY date");
$holidays_stmt->bind_param("i", $year);
$holidays_stmt->execute();
$holidays_result = $holidays_stmt->get_result();
$holidays = [];

while ($row = $holidays_result->fetch_assoc()) {
    $holidays[$row['date']] = $row;
}

// Generate months for the year selector
$years = range($current_year - 2, $current_year + 2);
$months = [
    '01' => 'January',
    '02' => 'February',
    '03' => 'March',
    '04' => 'April',
    '05' => 'May',
    '06' => 'June',
    '07' => 'July',
    '08' => 'August',
    '09' => 'September',
    '10' => 'October',
    '11' => 'November',
    '12' => 'December'
];

// Function to generate calendar
function generateCalendar($year, $month, $holidays)
{
    $first_day = date('N', strtotime("$year-$month-01"));
    $days_in_month = date('t', strtotime("$year-$month-01"));
    $calendar = [];
    $day = 1;

    for ($i = 0; $i < 6; $i++) {
        for ($j = 1; $j <= 7; $j++) {
            if (($i == 0 && $j < $first_day) || $day > $days_in_month) {
                $calendar[$i][$j] = null;
            } else {
                $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                $calendar[$i][$j] = [
                    'day' => $day,
                    'date' => $date,
                    'is_holiday' => isset($holidays[$date]),
                    'holiday_data' => $holidays[$date] ?? null
                ];
                $day++;
            }
        }
    }

    return $calendar;
}

$calendar = generateCalendar($year, $month, $holidays);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Holiday Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Remix Icon CDN -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- style css -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- sidebar css -->
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <!-- holiday calender css -->
    <link rel="stylesheet" href="assets/css/holiday_calender.css">
    <style>

    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include('sidebar.php') ?>.

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4"><i class="fas fa-calendar-day me-2"></i> Holiday Calendar</h2>

            <!-- Calendar Navigation -->
            <div class="calendar-container">
                <div class="calendar-header">
                    <h3 class="calendar-title"><?= date('F Y', strtotime("$year-$month-01")) ?></h3>
                    <div class="calendar-nav">
                        <a href="?month=<?= $month ?>&year=<?= $year - 1 ?>" class="btn btn-outline-primary">
                            <i class="fas fa-chevron-left"></i> Prev Year
                        </a>
                        <select class="form-select" onchange="window.location.href='?month='+this.value+'&year=<?= $year ?>'">
                            <?php foreach ($months as $key => $name): ?>
                                <option value="<?= $key ?>" <?= $key == $month ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-select" onchange="window.location.href='?month=<?= $month ?>&year='+this.value">
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endforeach; ?>
                        </select>
                        <a href="?month=<?= $month ?>&year=<?= $year + 1 ?>" class="btn btn-outline-primary">
                            Next Year <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <table class="calendar-table">
                            <thead>
                                <tr>
                                    <th>Mon</th>
                                    <th>Tue</th>
                                    <th>Wed</th>
                                    <th>Thu</th>
                                    <th>Fri</th>
                                    <th>Sat</th>
                                    <th>Sun</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($calendar as $week): ?>
                                    <tr>
                                        <?php foreach ($week as $day => $data): ?>
                                            <?php if ($data === null): ?>
                                                <td class="other-month"></td>
                                            <?php else: ?>
                                                <?php
                                                $is_today = $data['date'] == date('Y-m-d');
                                                $is_holiday = $data['is_holiday'];
                                                $cell_class = $is_today ? 'today' : '';
                                                $cell_class .= $is_holiday ? ' holiday-cell' : '';
                                                ?>
                                                <td class="<?= $cell_class ?>">
                                                    <div class="calendar-day"><?= $data['day'] ?></div>
                                                    <?php if ($is_holiday): ?>
                                                        <span class="holiday-type type-<?= $data['holiday_data']['type'] ?>">
                                                            <?= ucfirst($data['holiday_data']['type']) ?>
                                                        </span>
                                                        <div class="holiday-name"><?= $data['holiday_data']['name'] ?></div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>


                    <div class="col-md-4">
                        <!-- Holiday Types Legend -->
                        <div class="holiday-list">
                            <h4 class="mb-4"><i class="fas fa-info-circle me-2"></i> Holiday Types</h4>
                            <div class="holiday-item">
                                <span class="badge type-national me-2">National</span>
                                Government declared national holidays
                            </div>
                            <div class="holiday-item">
                                <span class="badge type-company me-2">Company</span>
                                Organization-specific holidays
                            </div>
                            <div class="holiday-item">
                                <span class="badge type-religious me-2">Religious</span>
                                Religious festivals and observances
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>