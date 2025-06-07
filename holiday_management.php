<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Add Holiday
if (isset($_POST['add_holiday'])) {
    $name = trim($_POST['name']);
    $date = $_POST['date'];
    $type = $_POST['type'];

    if (!empty($name) && !empty($date) && !empty($type)) {
        $stmt = $conn->prepare("INSERT INTO holidays (name, date, type) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name=?, type=?");
        $stmt->bind_param("sssss", $name, $date, $type, $name, $type);
        $stmt->execute();
    }
    header("Location: holiday_management.php");
    exit;
}

// Delete Holiday
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM holidays WHERE id = $id");
    header("Location: holiday_management.php");
    exit;
}

// Fetch Holidays
$holidays = $conn->query("SELECT * FROM holidays ORDER BY date ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Holiday Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <!-- custom css -->
     <link rel="stylesheet" href="assets/css/sidebar.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="container mt-4">
    <h2>Holiday Management</h2>

    <form method="POST" class="row g-3 my-3">
        <div class="col-md-4">
            <input type="text" name="name" class="form-control" placeholder="Holiday Name" required>
        </div>
        <div class="col-md-3">
            <input type="date" name="date" class="form-control" required>
        </div>
        <div class="col-md-3">
            <select name="type" class="form-select" required>
                <option value="">Select Type</option>
                <option value="Public">Public</option>
                <option value="Restricted">Restricted</option>
                <option value="Optional">Optional</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" name="add_holiday" class="btn btn-success w-100">Add</button>
        </div>
    </form>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>#</th>
                <th>Holiday Name</th>
                <th>Date</th>
                <th>Type</th>
                <th>Created At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($holidays->num_rows > 0): $count = 1; ?>
            <?php while ($row = $holidays->fetch_assoc()): ?>
                <tr>
                    <td><?= $count++ ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= $row['date'] ?></td>
                    <td><?= $row['type'] ?></td>
                    <td><?= $row['created_at'] ?></td>
                    <td>
                        <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this holiday?')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6" class="text-center">No holidays found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
