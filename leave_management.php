<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "attendance_system");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Handle Approve/Reject actions
if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);
    if (in_array($action, ['approved', 'rejected'])) {
        $stmt = $conn->prepare("UPDATE leave_applications SET status=? WHERE id=?");
        $stmt->bind_param("si", $action, $id);
        $stmt->execute();
    }
    header("Location: leave_management.php");
    exit;
}

// Fetch leave stats
$stats = $conn->query("SELECT 
    COUNT(*) AS total, 
    SUM(status='pending') AS pending, 
    SUM(status='approved') AS approved, 
    SUM(status='rejected') AS rejected 
FROM leave_applications")->fetch_assoc();

// Fetch leave applications
$query = "SELECT la.*, e.username, lt.name AS leave_type
            FROM leave_applications la
            JOIN employees e ON la.employee_id = e.id
            JOIN leave_types lt ON la.leave_type_id = lt.id
            ORDER BY la.created_at DESC;
            ";
$applications = $conn->query($query);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Leave Requests - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <!-- custom css -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
        body {
            margin-left: 290px;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="container py-3">
        <h2 class="mb-4">Leave Requests</h2>

        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card shadow-lg rounded-4" style="border-left: 4px solid var(--primary);">
                    <div class="card-body d-flex flex-column align-items-start">
                        <h5 class="fw-semibold">Total</h5>
                        <h3><?= $stats['total'] ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-lg rounded-4" style="border-left: 4px solid var(--primary);">
                    <div class="card-body d-flex flex-column align-items-start">
                        <h5 class="fw-semibold">Pending</h5>
                        <h3><?= $stats['pending'] ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-lg rounded-4" style="border-left: 4px solid var(--primary);">
                    <div class="card-body d-flex flex-column align-items-start">
                        <h5 class="fw-semibold">Approved</h5>
                        <h3><?= $stats['approved'] ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-lg rounded-4" style="border-left: 4px solid var(--primary);">
                    <div class="card-body d-flex flex-column align-items-start">
                        <h5 class="fw-semibold">Rejected</h5>
                        <h3><?= $stats['rejected'] ?></h3>
                    </div>
                </div>
            </div>
        </div>


        <!-- Leave Requests Table -->
        <div class="card shadow-lg border-0 rounded-4 mb-4">
            <div class="card-header bg-gradient text-white fw-semibold" style="background: linear-gradient(to right, #0f2027, #203a43, #2c5364);">
                <i class="fas fa-folder-open me-2"></i>All Applications
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle mb-0">
                        <thead class="table-light text-dark">
                            <tr>
                                <th>ID</th>
                                <th>Employee</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Applied At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $applications->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['username']) ?></td>
                                    <td><?= htmlspecialchars($row['reason']) ?></td>
                                    <td>
                                        <?php
                                        $badge = match ($row['status']) {
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'pending'  => 'warning',
                                            default => 'secondary',
                                        };
                                        ?>
                                        <span class="badge rounded-pill bg-<?= $badge ?> px-3 py-2">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $row['created_at'] ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <a href="?action=approved&id=<?= $row['id'] ?>" class="btn btn-sm btn-success me-1">
                                                <i class="fas fa-check-circle me-1"></i>Approve
                                            </a>
                                            <a href="?action=rejected&id=<?= $row['id'] ?>" class="btn btn-sm btn-danger">
                                                <i class="fas fa-times-circle me-1"></i>Reject
                                            </a>
                                        <?php else: ?>
                                            <small class="text-muted">
                                                <strong>By:</strong> <?= $row['processed_by'] ?><br>
                                                <strong>On:</strong> <?= $row['processed_at'] ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</body>

</html>