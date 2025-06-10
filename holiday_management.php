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
    <!-- Remix Icon CDN -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <!-- custom css -->
    <link rel="stylesheet" href="assets/css/sidebar.css">

    <style>
        .main-content {
            margin-left: 270px;
            /* Adjust this to match sidebar width */
            padding: 20px;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container mt-4">
            <h2>Holiday Management</h2>

            <div class="card shadow-lg border-0 rounded-4 mb-4">
                <div class="card-header bg-gradient text-white fw-semibold" style="background: linear-gradient(to right, #11998e, #38ef7d);">
                    <i class="fas fa-calendar-plus me-2"></i>Add New Holiday
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Add New Holiday</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Holiday Name</label>
                                    <input type="text" name="holiday_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Date</label>
                                    <input type="date" name="holiday_date" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Type</label>
                                    <select name="holiday_type" class="form-select" required>
                                        <option value="national">National Holiday</option>
                                        <option value="company">Company Holiday</option>
                                        <option value="religious">Religious Holiday</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" name="add_holiday" class="btn btn-primary">Add Holiday</button>
                            </div>
                        </div>
                    </form>

                </div>
            </div>


            <div class="card shadow-lg border-0 rounded-4 mb-4">
                <div class="card-header bg-gradient text-white fw-semibold" style="background: linear-gradient(to right, #ff416c, #ff4b2b);">
                    <i class="fas fa-calendar-alt me-2"></i>Holiday List
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
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
                                            <td><i class="far fa-calendar-alt me-1 text-secondary"></i><?= $row['date'] ?></td>
                                            <td>
                                                <span class="badge bg-primary rounded-pill px-3 py-2">
                                                    <?= $row['type'] ?>
                                                </span>
                                            </td>
                                            <td><?= $row['created_at'] ?></td>
                                            <td>
                                                <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger shadow-sm" onclick="return confirm('Delete this holiday?')">
                                                    <i class="fas fa-trash-alt me-1"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="fas fa-info-circle me-2"></i>No holidays found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</body>

</html>