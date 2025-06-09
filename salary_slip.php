<?php
$id = $_GET['id'];
$conn = new mysqli("localhost", "root", "", "attendance_system");
$stmt = $conn->prepare("SELECT s.*, e.username, e.position FROM salaries s JOIN employees e ON s.employee_id = e.id WHERE s.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$salary = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Slip</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }
        .salary-slip {
            max-width: 800px;
            margin: auto;
            background: #fff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(to right, #0575e6, #00f260);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        .slip-title {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .section-title {
            background: #f1f1f1;
            padding: 10px;
            font-weight: bold;
        }
        .table td, .table th {
            vertical-align: middle;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                background: white;
            }
        }
    </style>
</head>
<body>
<div class="salary-slip">
    <div class="header">
        <div class="slip-title">Salary Slip</div>
        <div><?= date('F Y', strtotime($salary['paid_at'])) ?></div>
    </div>

    <div class="my-4 row">
        <div class="col-md-6">
            <strong>Employee Name:</strong> <?= htmlspecialchars($salary['username']) ?><br>
            <strong>Designation:</strong> <?= htmlspecialchars($salary['position']) ?>
        </div>
        <div class="col-md-6 text-md-end">
            <strong>Month:</strong> <?= $salary['month'] ?><br>
            <strong>Year:</strong> <?= $salary['year'] ?>
        </div>
    </div>

    <div class="section-title">Earnings & Deductions</div>
    <table class="table table-bordered">
        <thead class="table-light">
            <tr>
                <th>Earnings</th>
                <th>Amount (₹)</th>
                <th>Deductions</th>
                <th>Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Basic</td>
                <td><?= number_format($salary['basic'], 2) ?></td>
                <td>PF</td>
                <td><?= number_format($salary['pf'], 2) ?></td>
            </tr>
            <tr>
                <td>HRA</td>
                <td><?= number_format($salary['hra'], 2) ?></td>
                <td>Tax</td>
                <td><?= number_format($salary['tax'], 2) ?></td>
            </tr>
            <tr>
                <td>Bonus</td>
                <td><?= number_format($salary['bonus'], 2) ?></td>
                <td></td>
                <td></td>
            </tr>
            <tr class="table-warning">
                <th>Total Earnings</th>
                <th>
                    ₹<?= number_format($salary['basic'] + $salary['hra'] + $salary['bonus'], 2) ?>
                </th>
                <th>Total Deductions</th>
                <th>
                    ₹<?= number_format($salary['pf'] + $salary['tax'], 2) ?>
                </th>
            </tr>
            <tr class="table-success">
                <th colspan="3" class="text-end">Net Salary (in ₹)</th>
                <th>₹<?= number_format($salary['amount'], 2) ?></th>
            </tr>
        </tbody>
    </table>

    <div class="text-center mt-4 no-print">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> Print Salary Slip
        </button>
    </div>
</div>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
