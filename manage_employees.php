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
            <h2 class="mb-4">Manage Employees</h2>
            <div class="row">
                <div class="col-md-9">

                    <!-- Employee Table -->
                    <div class="card shadow-lg border-0 rounded-4">
                        <div class="card-header bg-gradient text-white fw-semibold" style="background: linear-gradient(to right, #0062E6, #33AEFF);">
                            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Employee List</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light text-center">
                                        <tr>
                                            <th scope="col">ID</th>
                                            <th scope="col">Username</th>
                                            <th scope="col">Email</th>
                                            <th scope="col">Department</th>
                                            <th scope="col">Role</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-center">
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td class="text-muted"><?= $row['id'] ?></td>
                                                <td class="fw-semibold"><?= htmlspecialchars($row['username']) ?></td>
                                                <td><?= htmlspecialchars($row['email']) ?></td>
                                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['department']) ?></span></td>
                                                <td>
                                                    <span class="badge <?= $row['role'] === 'admin' ? 'bg-danger' : 'bg-secondary' ?>">
                                                        <?= ucfirst($row['role']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="manage_employees.php?edit=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="manage_employees.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this employee?')">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <!-- Employee Form -->
                    <div class="card shadow-lg border-0 rounded-4 mb-4">
                        <div class="card-header text-white fw-semibold" style="background: linear-gradient(to right, #0f2027, #203a43, #2c5364);">
                            <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i><?= $edit_employee ? 'Edit' : 'Add' ?> Employee</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="id" value="<?= $edit_employee['id'] ?? '' ?>">

                                <div class="form-floating mb-3">
                                    <input type="text" name="username" class="form-control" id="username" placeholder="Username" required value="<?= $edit_employee['username'] ?? '' ?>">
                                    <label for="username">Username</label>
                                </div>

                                <div class="form-floating mb-3">
                                    <input type="email" name="email" class="form-control" id="email" placeholder="Email" required value="<?= $edit_employee['email'] ?? '' ?>">
                                    <label for="email">Email</label>
                                </div>

                                <div class="form-floating mb-3">
                                    <input type="text" name="department" class="form-control" id="department" placeholder="Department" value="<?= $edit_employee['department'] ?? '' ?>">
                                    <label for="department">Department</label>
                                </div>

                                <div class="form-floating mb-4">
                                    <select name="role" class="form-select" id="role" required>
                                        <option value="employee" <?= (isset($edit_employee['role']) && $edit_employee['role'] == 'employee') ? 'selected' : '' ?>>Employee</option>
                                        <option value="admin" <?= (isset($edit_employee['role']) && $edit_employee['role'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                    <label for="role">Role</label>
                                </div>

                                <button type="submit" class="btn btn-gradient-success px-4">
                                    <i class="fas fa-check-circle me-1"></i><?= $edit_employee ? 'Update' : 'Add' ?>
                                </button>

                                <?php if ($edit_employee): ?>
                                    <a href="manage_employees.php" class="btn btn-outline-secondary ms-2 px-4">
                                        <i class="fas fa-times-circle me-1"></i>Cancel
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</body>

</html>