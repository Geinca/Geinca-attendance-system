<?php
date_default_timezone_set('Asia/Kolkata');
session_start();

// Redirect if not logged in
if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit;
}

// Database connection
require_once 'db_config.php'; // Assuming you have a separate config file

// Fetch employee details
$employee_id = $_SESSION['employee_id'];
$stmt = $conn->prepare("SELECT username, available_leaves FROM employees WHERE id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    die("<div class='alert alert-danger'>No employee found for ID $employee_id</div>");
}

// Fetch leave types from database
$leave_types_result = $conn->query("SELECT id, name FROM leave_types WHERE is_active = 1");
$leave_types = $leave_types_result->fetch_all(MYSQLI_ASSOC);

// Handle leave application submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_leave'])) {
    // Validate and sanitize inputs
    $leave_type = filter_input(INPUT_POST, 'leave_type', FILTER_VALIDATE_INT);
    $start_date = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
    $end_date = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);
    $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
    $document = $_FILES['document'] ?? null;
    
    // Basic validation
    if (empty($leave_type) || empty($start_date) || empty($end_date) || empty($reason)) {
        $error_message = "All fields are required except document";
    } elseif (strtotime($start_date) > strtotime($end_date)) {
        $error_message = "End date cannot be before start date";
    } else {
        try {
            // Calculate number of working days (excluding weekends)
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $end->modify('+1 day'); // Include end date in calculation
            
            $interval = $start->diff($end);
            $days = $interval->days;
            $weekend_days = 0;
            
            for ($i = 0; $i < $days; $i++) {
                $modify = $start->modify('+1 day');
                $day_of_week = $modify->format('N');
                if ($day_of_week >= 6) { // 6 and 7 are Saturday and Sunday
                    $weekend_days++;
                }
            }
            
            $working_days = $days - $weekend_days;
            
            // Check leave balance
            if ($working_days > $employee['available_leaves']) {
                $error_message = "You don't have enough leave balance";
            } else {
                // Handle file upload if present
                $document_path = '';
                if ($document && $document['size'] > 0) {
                    // Validate file upload
                    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                    $max_size = 2 * 1024 * 1024; // 2MB
                    
                    if (!in_array($document['type'], $allowed_types)) {
                        $error_message = "Only PDF, JPG, and PNG files are allowed";
                    } elseif ($document['size'] > $max_size) {
                        $error_message = "File size must be less than 2MB";
                    } else {
                        $upload_dir = 'uploads/leave_documents/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_ext = pathinfo($document['name'], PATHINFO_EXTENSION);
                        $filename = 'leave_' . $employee_id . '_' . time() . '.' . $file_ext;
                        $target_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($document['tmp_name'], $target_path)) {
                            $document_path = $target_path;
                        } else {
                            $error_message = "Failed to upload document";
                        }
                    }
                }
                
                if (empty($error_message)) {
                    // Insert leave application using transaction
                    $conn->begin_transaction();
                    
                    try {
                        $stmt = $conn->prepare("INSERT INTO leave_applications 
                                            (employee_id, leave_type_id, start_date, end_date, reason, document_path, status) 
                                            VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                        $stmt->bind_param("iissss", $employee_id, $leave_type, $start_date, $end_date, $reason, $document_path);
                        
                        if ($stmt->execute()) { 
                            $success_message = "Leave application submitted successfully!";
                            $conn->commit();
                        } else {
                            throw new Exception("Error submitting leave application: " . $conn->error);
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error_message = $e->getMessage();
                        
                        // Clean up uploaded file if transaction failed
                        if (!empty($document_path) && file_exists($document_path)) {
                            unlink($document_path);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $error_message = "Error processing dates: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Leave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
        .main-content {
            padding: 20px;
        }
        
        .leave-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .leave-balance-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
        }
        
        .balance-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .form-section {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .btn-submit {
            background-color: var(--primary);
            color: white;
            padding: 10px 25px;
            font-weight: 500;
        }
        
        .btn-submit:hover {
            background-color: #0066cc;
            color: white;
        }
        
        .date-preview {
            background-color: rgba(1, 159, 226, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .recent-leave-item {
            transition: all 0.2s;
        }
        
        .recent-leave-item:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include('sidebar.php') ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4"><i class="fas fa-calendar-minus me-2"></i> Apply for Leave</h2>
            
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($success_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-8">
                    <!-- Leave Application Form -->
                    <div class="leave-card">
                        <form method="POST" enctype="multipart/form-data" id="leaveForm">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Leave Type</label>
                                    <select name="leave_type" class="form-select" required>
                                        <option value="">Select Leave Type</option>
                                        <?php foreach ($leave_types as $type): ?>
                                            <option value="<?= htmlspecialchars($type['id']) ?>">
                                                <?= htmlspecialchars($type['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Available Leave Balance</label>
                                    <input type="text" class="form-control" 
                                           value="<?= htmlspecialchars($employee['available_leaves'] ?? 0) ?> days" disabled>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" id="startDate" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control" id="endDate" required>
                                </div>
                            </div>
                            
                            <div class="date-preview" id="datePreview">
                                <div class="text-center text-muted">Select dates to see leave duration</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Reason for Leave</label>
                                <textarea name="reason" class="form-control" rows="3" required 
                                          placeholder="Please provide a detailed reason for your leave"></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Supporting Document (Optional)</label>
                                <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Upload PDF, JPG or PNG files (max 2MB)</small>
                            </div>
                            
                            <button type="submit" name="apply_leave" class="btn btn-submit">
                                <i class="fas fa-paper-plane me-2"></i> Submit Application
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Leave Balance Information -->
                    <div class="leave-balance-card">
                        <h5 class="mb-4"><i class="fas fa-calendar-check me-2"></i> Your Leave Balance</h5>
                        <div class="text-center">
                            <div class="balance-number"><?= htmlspecialchars($employee['available_leaves'] ?? 0) ?></div>
                            <div>days remaining</div>
                        </div>
                    </div>
                    
                    <!-- Recent Leave Applications -->
                    <div class="leave-card">
                        <h5 class="mb-4"><i class="fas fa-history me-2"></i> Recent Applications</h5>
                        <?php
                        $recent_leaves = $conn->query("SELECT l.*, t.name as type_name 
                                                     FROM leave_applications l
                                                     JOIN leave_types t ON l.leave_type_id = t.id
                                                     WHERE l.employee_id = $employee_id
                                                     ORDER BY l.created_at DESC
                                                     LIMIT 5");
                        
                        if ($recent_leaves->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while ($leave = $recent_leaves->fetch_assoc()): ?>
                                    <a href="leave_details.php?id=<?= $leave['id'] ?>" 
                                       class="list-group-item list-group-item-action recent-leave-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($leave['type_name']) ?></strong>
                                                <div class="small text-muted">
                                                    <?= date('d M Y', strtotime($leave['start_date'])) ?> - 
                                                    <?= date('d M Y', strtotime($leave['end_date'])) ?>
                                                </div>
                                            </div>
                                            <span class="badge bg-<?= 
                                                $leave['status'] == 'approved' ? 'success' : 
                                                ($leave['status'] == 'rejected' ? 'danger' : 'warning') ?>">
                                                <?= ucfirst($leave['status']) ?>
                                            </span>
                                        </div>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                            <div class="text-end mt-2">
                                <a href="leave_history.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-3">
                                No recent leave applications
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum dates to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('startDate').min = today;
            document.getElementById('endDate').min = today;
            
            // When start date changes, update end date min
            document.getElementById('startDate').addEventListener('change', function() {
                const startDate = this.value;
                document.getElementById('endDate').min = startDate;
                updateDatePreview();
            });
            
            document.getElementById('endDate').addEventListener('change', updateDatePreview);
            
            // Form validation
            document.getElementById('leaveForm').addEventListener('submit', function(e) {
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                
                if (!startDate || !endDate) {
                    e.preventDefault();
                    alert('Please select both start and end dates');
                    return false;
                }
                
                if (new Date(startDate) > new Date(endDate)) {
                    e.preventDefault();
                    alert('End date cannot be before start date');
                    return false;
                }
                
                return true;
            });
        });
        
        function updateDatePreview() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const preview = document.getElementById('datePreview');
            
            if (startDate && endDate) {
                const workingDays = calculateWorkingDays(startDate, endDate);
                const startFormatted = formatDate(startDate);
                const endFormatted = formatDate(endDate);
                
                preview.innerHTML = `
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong>From:</strong> ${startFormatted}
                        </div>
                        <div>
                            <strong>To:</strong> ${endFormatted}
                        </div>
                    </div>
                    <div class="text-center mt-2">
                        <strong>${workingDays}</strong> working day(s) will be deducted from your balance
                    </div>
                `;
            }
        }
        
        function calculateWorkingDays(startDate, endDate) {
            if (!startDate || !endDate) return 0;
            
            const start = new Date(startDate);
            const end = new Date(endDate);
            end.setDate(end.getDate() + 1); // Include end date
            
            let count = 0;
            const curDate = new Date(start.getTime());
            
            while (curDate <= end) {
                const dayOfWeek = curDate.getDay();
                if (dayOfWeek !== 0 && dayOfWeek !== 6) { // Not Sunday or Saturday
                    count++;
                }
                curDate.setDate(curDate.getDate() + 1);
            }
            
            return count;
        }
        
        function formatDate(dateString) {
            const options = { day: 'numeric', month: 'short', year: 'numeric' };
            return new Date(dateString).toLocaleDateString('en-IN', options);
        }
    </script>
</body>
</html>