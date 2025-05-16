<?php
session_start();
$conn = new mysqli("localhost", "root", "", "attendance_system");

$login_error = '';
$register_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'login') {
        // Handle login
        $username = $_POST['username_login'];
        $password = $_POST['password_login'];

        $stmt = $conn->prepare("SELECT id, password FROM employees WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $hashed_password);
            $stmt->fetch();
            if (password_verify($password, $hashed_password)) {
                $_SESSION['employee_id'] = $id;
                header("Location: timer.php");
                exit;
            } else {
                $login_error = "Invalid username or password";
            }
        } else {
            $login_error = "Invalid username or password";
        }

    } elseif (isset($_POST['action']) && $_POST['action'] == 'register') {
        // Handle register
        $username = trim($_POST['username_register']);
        $password = $_POST['password_register'];
        $confirm_password = $_POST['confirm_password_register'];

        if ($password !== $confirm_password) {
            $register_error = "Passwords do not match.";
        } else {
            // Check if username exists
            $stmt = $conn->prepare("SELECT id FROM employees WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $register_error = "Username already taken.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO employees (username, password) VALUES (?, ?)");
                $stmt->bind_param("ss", $username, $hashed_password);
                if ($stmt->execute()) {
                    $_SESSION['employee_id'] = $conn->insert_id;
                    header("Location: timer.php");
                    exit;
                } else {
                    $register_error = "Registration failed. Try again.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login / Register</title>

   
    <link rel="stylesheet" href="login.css">
</head>
<body>

<h2>Employee Portal</h2>

<div>
    <div id="loginTab" class="tab active" onclick="showTab('login')">Login</div>
    <div id="registerTab" class="tab" onclick="showTab('register')">Register</div>
</div>

<div id="loginForm" class="form-section active">
    <form method="post">
        <input type="hidden" name="action" value="login">
        <label>Username:</label>
        <input type="text" name="username_login" required>
        <label>Password:</label>
        <input type="password" name="password_login" required>
        <button type="submit">Login</button>
        <?php if ($login_error) echo "<div class='error'>$login_error</div>"; ?>
    </form>
</div>

<div id="registerForm" class="form-section">
    <form method="post">
        <input type="hidden" name="action" value="register">
        <label>Username:</label>
        <input type="text" name="username_register" required>
        <label>Password:</label>
        <input type="password" name="password_register" required>
        <label>Confirm Password:</label>
        <input type="password" name="confirm_password_register" required>
        <button type="submit">Register</button>
        <?php if ($register_error) echo "<div class='error'>$register_error</div>"; ?>
    </form>
</div>

<script>
function showTab(tab) {
    document.getElementById('loginForm').classList.remove('active');
    document.getElementById('registerForm').classList.remove('active');
    document.getElementById('loginTab').classList.remove('active');
    document.getElementById('registerTab').classList.remove('active');

    if (tab === 'login') {
        document.getElementById('loginForm').classList.add('active');
        document.getElementById('loginTab').classList.add('active');
    } else {
        document.getElementById('registerForm').classList.add('active');
        document.getElementById('registerTab').classList.add('active');
    }
}
</script>

</body>
</html>
