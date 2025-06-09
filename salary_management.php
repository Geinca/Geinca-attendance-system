<?php
session_start();
$conn = new mysqli("localhost", "root", "", "attendance_system");

// Add or Update Salary
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_POST['id'] ?? null;
    $employee_id = $_POST['employee_id'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    $amount = $_POST['amount'];

    if ($id) {
        $stmt = $conn->prepare("UPDATE salaries SET employee_id=?, month=?, year=?, amount=? WHERE id=?");
        $stmt->bind_param("issdi", $employee_id, $month, $year, $amount, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO salaries (employee_id, month, year, amount) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issd", $employee_id, $month, $year, $amount);
    }
    $stmt->execute();
    header("Location: salary_management.php");
    exit;
}

// Delete
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM salaries WHERE id = ?");
    $stmt->bind_param("i", $_GET['delete']);
    $stmt->execute();
    header("Location: salary_management.php");
    exit;
}

// Edit
$edit_salary = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM salaries WHERE id = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $edit_salary = $stmt->get_result()->fetch_assoc();
}

// Get employees
$employees = $conn->query("SELECT id, username FROM employees ORDER BY username");

// Get salary list
$salaries = $conn->query("SELECT s.*, e.username FROM salaries s JOIN employees e ON s.employee_id = e.id ORDER BY s.paid_at DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Salary Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <!-- custom css -->
    <link rel="stylesheet" href="assets/css/sidebar.css">

    <style>
        body {
            background-color: #f8f9fa;
        }

        @media (min-width: 992px) {
            .col-lg-10 {
                flex: 0 0 auto;
                width: 80%;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">

            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 p-0">
                <?php include 'sidebar.php'; ?>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 py-4" style="margin-left: 270px;">
                <div class="container">

                    <!-- Form -->
                    <div class="card shadow-lg border-0 rounded-4 mb-4">
                        <div class="card-header text-white" style="background: linear-gradient(to right, #0575e6, #00f260);">
                            <i class="fas fa-money-check-alt me-2"></i><?= $edit_salary ? 'Edit' : 'Add' ?> Salary
                        </div>
                        <div class="card-body">
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="id" value="<?= $edit_salary['id'] ?? '' ?>">
                                <div class="col-md-3">
                                    <label class="form-label">Employee</label>
                                    <select name="employee_id" class="form-select" required>
                                        <option value="">Select Employee</option>
                                        <?php while ($emp = $employees->fetch_assoc()): ?>
                                            <option value="<?= $emp['id'] ?>" <?= isset($edit_salary['employee_id']) && $edit_salary['employee_id'] == $emp['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($emp['username']) ?>
                                            </option>
                                        <?php endwhile ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Month</label>
                                    <input type="text" name="month" class="form-control" placeholder="e.g. June" required value="<?= $edit_salary['month'] ?? '' ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Year</label>
                                    <input type="number" name="year" class="form-control" placeholder="2025" required value="<?= $edit_salary['year'] ?? date('Y') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Amount (₹)</label>
                                    <input type="number" name="amount" step="0.01" class="form-control" required value="<?= $edit_salary['amount'] ?? '' ?>">
                                </div>
                                <div class="col-md-2 d-grid">
                                    <label class="form-label invisible">Submit</label>
                                    <button type="submit" class="btn btn-success">
                                        <?= $edit_salary ? 'Update' : 'Add' ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="card shadow border-0 rounded-4">
                        <div class="card-header bg-dark text-white">
                            <i class="fas fa-table me-2"></i>Salary Records
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-hover table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Employee</th>
                                        <th>Month</th>
                                        <th>Year</th>
                                        <th>Amount (₹)</th>
                                        <th>Paid At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($salaries->num_rows > 0): $i = 1; ?>
                                        <?php while ($row = $salaries->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= $i++ ?></td>
                                                <td><?= htmlspecialchars($row['username']) ?></td>
                                                <td><?= $row['month'] ?></td>
                                                <td><?= $row['year'] ?></td>
                                                <td><?= number_format($row['amount'], 2) ?></td>
                                                <td><?= $row['paid_at'] ?></td>
                                                <td>
                                                    <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this record?')"><i class="fas fa-trash"></i></a>
                                                    <a href="salary_slip.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info" target="_blank"><i class="fas fa-file-invoice"></i> Slip</a>
                                                </td>
                                            </tr>
                                        <?php endwhile ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">No salary records found.</td>
                                        </tr>
                                    <?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div> <!-- /.container -->
            </div> <!-- /.main-content -->

        </div> <!-- /.row -->
    </div> <!-- /.container-fluid -->
</body>

</html>