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
                header("Location: dashboard.php");
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
                    header("Location: dashboard.php");
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #019FE2;
            background: linear-gradient(90deg, rgba(1, 159, 226, 1) 42%, rgba(63, 55, 201, 1) 92%);
        }

        .card {
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            background-color: rgba(17, 25, 40, 0.75);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.125);
        }

        .tab-active {
            position: relative;
        }

        .tab-active:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60%;
            height: 3px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            border-radius: 3px;
        }

        .input-field {
            transition: all 0.3s ease;
        }

        .input-field:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>

<body class="from-gray-900 via-purple-900 to-violet-800 min-h-screen flex items-center justify-center p-4">
    <div class="card w-full max-w-xs sm:max-w-sm md:max-w-md p-6 sm:p-8 shadow-xl">
        <div class="text-center mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-white mb-2">Welcome Back</h1>
            <p class="text-gray-300">Manage your work attendance</p>
        </div>

        <div class="flex mb-6 border-b border-gray-700">
            <button id="loginTab" onclick="showTab('login')" class="tab-active py-3 px-4 font-medium text-white flex-1 text-center">
                Sign In
            </button>
            <button id="registerTab" onclick="showTab('register')" class="py-3 px-4 font-medium text-gray-400 flex-1 text-center hover:text-white transition">
                Register
            </button>
        </div>

        <div id="loginForm">
            <form method="post" class="space-y-6">
                <input type="hidden" name="action" value="login">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Username</label>
                    <input type="text" name="username_login" class="input-field sm:text-base w-full px-4 py-3 bg-gray-800 border border-gray-700 sm:px-4 py-2 sm:py-3 rounded-lg text-white focus:outline-none focus:border-blue-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                    <input type="password" name="password_login" class="input-field w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-blue-500" required>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 rounded-lg font-medium hover:opacity-90 transition duration-300 shadow-lg">
                    Login
                </button>
                <?php if ($login_error): ?>
                    <div class="mt-4 p-3 bg-red-900/50 border border-red-700 text-red-100 rounded-lg text-sm">
                        <?php echo $login_error; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <div id="registerForm" class="hidden">
            <form method="post" class="space-y-6">
                <input type="hidden" name="action" value="register">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Username</label>
                    <input type="text" name="username_register" class="input-field w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-blue-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                    <input type="password" name="password_register" class="input-field w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-blue-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Confirm Password</label>
                    <input type="password" name="confirm_password_register" class="input-field w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-blue-500" required>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 rounded-lg font-medium hover:opacity-90 transition duration-300 shadow-lg">
                    Create Account
                </button>
                <?php if ($register_error): ?>
                    <div class="mt-4 p-3 bg-red-900/50 border border-red-700 text-red-100 rounded-lg text-sm">
                        <?php echo $register_error; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
        function showTab(tab) {
            // Hide all forms
            document.getElementById('loginForm').classList.add('hidden');
            document.getElementById('registerForm').classList.add('hidden');

            // Reset all tabs
            document.getElementById('loginTab').classList.remove('tab-active', 'text-white');
            document.getElementById('loginTab').classList.add('text-gray-400');
            document.getElementById('registerTab').classList.remove('tab-active', 'text-white');
            document.getElementById('registerTab').classList.add('text-gray-400');

            // Show selected tab
            if (tab === 'login') {
                document.getElementById('loginForm').classList.remove('hidden');
                document.getElementById('loginTab').classList.add('tab-active', 'text-white');
                document.getElementById('loginTab').classList.remove('text-gray-400');
            } else {
                document.getElementById('registerForm').classList.remove('hidden');
                document.getElementById('registerTab').classList.add('tab-active', 'text-white');
                document.getElementById('registerTab').classList.remove('text-gray-400');
            }
        }
    </script>
</body>

</html>