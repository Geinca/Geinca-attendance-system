<?php
// Connect to DB
$conn = new mysqli("localhost", "root", "", "attendance_system");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Handle Add/Edit Form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'] ?? '';
    $username = $_POST['username'];
    $email = $_POST['email'];
    $department = $_POST['department'];
    $role = $_POST['role'];

    if ($id) {
        $stmt = $conn->prepare("UPDATE employees SET username=?, email=?, department=?, role=? WHERE id=?");
        $stmt->bind_param("ssssi", $username, $email, $department, $role, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO employees (username, email, department, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $email, $department, $role);
    }

    $stmt->execute();
    header("Location: manage_employees.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $conn->query("DELETE FROM employees WHERE id = $delete_id");
    header("Location: manage_employees.php");
    exit;
}

// Get all employees
$result = $conn->query("SELECT * FROM employees ORDER BY id ASC");

// If editing
$edit_employee = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $edit_result = $conn->query("SELECT * FROM employees WHERE id = $edit_id");
    $edit_employee = $edit_result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Employees</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <!-- custom css -->
    <link rel="stylesheet" href="assets/css/sidebar.css">

    <style>
        body {
            margin-left: 290px;
            /* Make space for sidebar */
        }
    </style>
</head>

<body class="bg-light">
    <div class="d-flex">
        <?php include('sidebar.php'); ?>


        <div class="container py-3">
            <div class="row">
                <div class="col-md-12">
                    <h2 class="mb-4">Manage Employees</h2>

                    <!-- Employee Form -->
                    <div class="card mb-4">
                        <div class="card-header"><?= $edit_employee ? 'Edit' : 'Add' ?> Employee</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="id" value="<?= $edit_employee['id'] ?? '' ?>">
                                <div class="mb-3">
                                    <label>Username</label>
                                    <input type="text" name="username" class="form-control" required value="<?= $edit_employee['username'] ?? '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" required value="<?= $edit_employee['email'] ?? '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label>Department</label>
                                    <input type="text" name="department" class="form-control" value="<?= $edit_employee['department'] ?? '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label>Role</label>
                                    <select name="role" class="form-select" required>
                                        <option value="employee" <?= (isset($edit_employee['role']) && $edit_employee['role'] == 'employee') ? 'selected' : '' ?>>Employee</option>
                                        <option value="admin" <?= (isset($edit_employee['role']) && $edit_employee['role'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success"><?= $edit_employee ? 'Update' : 'Add' ?></button>
                                <?php if ($edit_employee): ?>
                                    <a href="manage_employees.php" class="btn btn-secondary ms-2">Cancel</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Employee Table -->
                    <div class="card">
                        <div class="card-header">Employee List</div>
                        <div class="card-body p-0">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Role</th>
                                        <th style="width: 140px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $row['id'] ?></td>
                                            <td><?= htmlspecialchars($row['username']) ?></td>
                                            <td><?= htmlspecialchars($row['email']) ?></td>
                                            <td><?= htmlspecialchars($row['department']) ?></td>
                                            <td><?= ucfirst($row['role']) ?></td>
                                            <td>
                                                <a href="manage_employees.php?edit=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                                <a href="manage_employees.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this employee?')">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endwhile ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>