<?php
date_default_timezone_set('Asia/Kolkata');
include 'db_config.php';

$today = date('Y-m-d');

// Fetch all employees
$employee_query = "
    SELECT 
        e.id, 
        e.name, 
        e.department,
        CASE 
            WHEN a.start_time IS NOT NULL THEN 'Present'
            ELSE 'Absent'
        END AS status
    FROM employees e
    LEFT JOIN (
        SELECT employee_id, start_time 
        FROM attendance 
        WHERE date = ?
    ) a ON e.id = a.employee_id
";

$stmt = $conn->prepare($employee_query);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Team Leaves</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <!-- Remix Icon CDN -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- custom css -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">

    <style>
        :root {
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --primary: #0066cc;
            --success: #28a745;
            --danger: #dc3545;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            transition: all 0.3s ease;
            padding: 20px;
            width: 80%;
        }

        .main-content-expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
        }

        .table-custom {
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .table-custom thead {
            background-color: var(--primary);
            color: white;
        }

        .table-custom th {
            border: none;
            padding: 15px;
        }

        .table-custom td {
            padding: 12px 15px;
            vertical-align: middle;
        }

        .badge-present {
            background-color: rgba(40, 167, 69, 0.2);
            color: var(--success);
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .badge-absent {
            background-color: rgba(220, 53, 69, 0.2);
            color: var(--danger);
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <!-- Modern Sidebar -->
    <?php include('sidebar.php') ?>

    <!-- Main Content -->
    <div class="main-content container my-4" id="mainContent">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-primary text-white rounded-top-4 py-3 d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-users me-2"></i>Team Leaves <small class="text-light ms-2">(<?= date('F j, Y', strtotime($today)) ?>)</small>
                </h4>
                <span class="badge bg-light text-primary fw-semibold"><?= date('l') ?></span>
            </div>

            <div class="card-body bg-light p-4 rounded-bottom-4">
                <div class="table-responsive">
                    <table class="table table-hover table-borderless align-middle mb-0">
                        <thead class="table-primary">
                            <tr>
                                <th scope="col">👨‍💼 Employee</th>
                                <th scope="col">🏢 Department</th>
                                <th scope="col">📊 Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="bg-white border-bottom">
                                    <td class="fw-medium"><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= htmlspecialchars($row['department']) ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'Present'): ?>
                                            <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill">Present</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger px-3 py-2 rounded-pill">Absent</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>


    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle sidebar
        document.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                document.getElementById('sidebar').classList.add('sidebar-collapsed');
                document.getElementById('mainContent').classList.add('main-content-expanded');
            }
        });
    </script>
</body>

</html>