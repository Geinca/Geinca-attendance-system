<?php
date_default_timezone_set('Asia/Kolkata');
include 'db_config.php';

$role = $_SESSION['role'];
$is_admin = ($role === 'admin');


// Fetch employee data
$stmt = $conn->prepare("SELECT name, email, phone, department, position, hire_date, address FROM employees WHERE id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();


function formatDate($date)
{
    return $date ? date('d M Y', strtotime($date)) : 'Not available';
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Employee Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Remix Icon CDN -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- style css -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- sidebar css -->
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
        /* General layout spacing */
        .main-content {
            padding: 2rem;
            background-color: #f8f9fa;
        }

        /* Profile card */
        .profile-card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            text-align: center;
        }

        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid #019FE2;
            margin-bottom: 1rem;
        }

        .info-item {
            margin-bottom: 1rem;
        }

        .info-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.85rem;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: 500;
            color: #343a40;
        }

        /* Form section */
        .form-section {
            background: #fff;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #019FE2;
            display: flex;
            align-items: center;
        }

        /* Buttons */
        .btn-save {
            background-color: #019FE2;
            color: white;
            font-weight: 500;
            border-radius: 0.5rem;
            padding: 0.5rem 1.25rem;
            transition: background-color 0.3s ease;
        }

        .btn-save:hover {
            background-color: #017bb3;
        }

        /* Alerts */
        .alert {
            border-radius: 0.75rem;
            font-weight: 500;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include('sidebar.php') ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-md-4">
                    <!-- Profile Card -->
                    <div class="profile-card">
                        <div class="profile-header">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($employee['name']) ?>&background=019FE2&color=fff&size=120"
                                alt="Profile" class="profile-pic">
                        </div>

                        <div class="info-item">
                            <div class="info-label">Employee ID</div>
                            <div class="info-value">EMP-<?= str_pad($employee_id, 4, '0', STR_PAD_LEFT) ?></div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">Name</div>
                            <div class="info-value"><?= htmlspecialchars($employee['name']) ?></div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">Department</div>
                            <div class="info-value"><?= htmlspecialchars($employee['department'] ?? 'Not specified') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">Position</div>
                            <div class="info-value"><?= htmlspecialchars($employee['position'] ?? 'Not specified') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">Hire Date</div>
                            <div class="info-value">
                                <?php echo isset($employee['hire_date']) ? htmlspecialchars($employee['hire_date']) : 'Not available'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <!-- Personal Information Read-Only Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-user-edit me-2"></i> Personal Information</h4>

                        <?php if ($is_admin): ?>
                            <!-- Editable Form for Admin -->
                            <form method="POST">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($employee['email']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($employee['phone']) ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($employee['address']) ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-save">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                            </form>
                        <?php else: ?>
                            <!-- Read-Only View for Employee -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <p class="form-control-plaintext"><?= htmlspecialchars($employee['email'] ?? 'N/A') ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <p class="form-control-plaintext"><?= htmlspecialchars($employee['phone'] ?? 'N/A') ?></p>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <p class="form-control-plaintext"><?= nl2br(htmlspecialchars($employee['address'] ?? 'N/A')) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>



                    <!-- Change Password Form -->
                    <?php if ($is_admin): ?>
                        <!-- Change Password Form for Admin -->
                        <div class="form-section">
                            <h4 class="section-title"><i class="fas fa-lock me-2"></i> Change Password</h4>
                            <form method="POST">
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" name="current_password" class="form-control">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">New Password</label>
                                        <input type="password" name="new_password" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-save">
                                    <i class="fas fa-key me-2"></i> Change Password
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple password strength indicator
        document.querySelector('input[name="new_password"]').addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.getElementById('password-strength');

            if (password.length === 0) {
                strengthIndicator.textContent = '';
                return;
            }

            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            const strengthText = ['Very Weak', 'Weak', 'Moderate', 'Strong', 'Very Strong'][strength - 1];
            const strengthColors = ['danger', 'warning', 'info', 'success', 'success'];

            strengthIndicator.textContent = `Strength: ${strengthText}`;
            strengthIndicator.className = `text-${strengthColors[strength - 1]}`;
        });
    </script>
</body>

</html>