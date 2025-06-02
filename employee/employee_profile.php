<?php
date_default_timezone_set('Asia/Kolkata');
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "attendance_system");
$employee_id = $_SESSION['employee_id'];

// Fetch employee data
$stmt = $conn->prepare("SELECT username, email, phone, department, position, hire_date, address FROM employees WHERE id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate and update profile
    if (!empty($email) && !empty($phone)) {
        $update_stmt = $conn->prepare("UPDATE employees SET email = ?, phone = ?, address = ? WHERE id = ?");
        $update_stmt->bind_param("sssi", $email, $phone, $address, $employee_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Refresh employee data
            $employee['email'] = $email;
            $employee['phone'] = $phone;
            $employee['address'] = $address;
        } else {
            $error_message = "Error updating profile: " . $conn->error;
        }
    }

    // Handle password change if provided
    if (!empty($current_password)) {
        // Verify current password
        $check_stmt = $conn->prepare("SELECT password FROM employees WHERE id = ?");
        $check_stmt->bind_param("i", $employee_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $db_password = $check_result->fetch_assoc()['password'];
        
        if (password_verify($current_password, $db_password)) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $pass_stmt = $conn->prepare("UPDATE employees SET password = ? WHERE id = ?");
                $pass_stmt->bind_param("si", $hashed_password, $employee_id);
                
                if ($pass_stmt->execute()) {
                    $success_message = $success_message ? $success_message . " Password updated!" : "Password updated successfully!";
                } else {
                    $error_message = $error_message ? $error_message . " Error updating password." : "Error updating password.";
                }
            } else {
                $error_message = $error_message ? $error_message . " New passwords don't match." : "New passwords don't match.";
            }
        } else {
            $error_message = $error_message ? $error_message . " Current password is incorrect." : "Current password is incorrect.";
        }
    }
}

function formatDate($date) {
    return $date ? date('d M Y', strtotime($date)) : 'Not available';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Employee Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- style css -->
     <link rel="stylesheet" href="../assets/css/style.css">
    <!-- sidebar css -->
     <link rel="stylesheet" href="../assets/css/sidebar.css">
    <style>
        
        .main-content {
            padding: 20px;
        }
        
        .profile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid rgba(1, 159, 226, 0.2);
            margin-right: 30px;
        }
        
        .info-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 1.1rem;
        }
        
        .form-section {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .section-title {
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .btn-save {
            background-color: var(--primary);
            color: white;
            padding: 10px 25px;
            font-weight: 500;
        }
        
        .btn-save:hover {
            background-color: #0066cc;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
   <?php include('../sidebar.php') ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-4">
                    <!-- Profile Card -->
                    <div class="profile-card">
                        <div class="profile-header">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($employee['username']) ?>&background=019FE2&color=fff&size=120" 
                                 alt="Profile" class="profile-pic">
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Employee ID</div>
                            <div class="info-value">EMP-<?= str_pad($employee_id, 4, '0', STR_PAD_LEFT) ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Name</div>
                            <div class="info-value"><?= htmlspecialchars($employee['username']) ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Department</div>
                            <div class="info-value"><?= htmlspecialchars($employee['department']) ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Position</div>
                            <div class="info-value"><?= htmlspecialchars($employee['position']) ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Hire Date</div>
                            <div class="info-value"><?= formatDate($employee['hire_date']) ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <!-- Personal Information Form -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-user-edit me-2"></i> Personal Information</h4>
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($employee['email']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?= htmlspecialchars($employee['phone']) ?>" required>
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
                    </div>
                    
                    <!-- Change Password Form -->
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