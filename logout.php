<?php
// logout.php

// Start the session
session_start();

// Store logout message before destroying session
$logout_message = "You have been successfully logged out.";

// Unset all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        
        .logout-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 40px;
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        .logout-icon {
            font-size: 4rem;
            color: #019FE2;
            margin-bottom: 20px;
        }
        
        .countdown {
            font-size: 1.2rem;
            color: #6c757d;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h2>Logged Out Successfully</h2>
        <p class="lead"><?php echo $logout_message; ?></p>
        <p>You will be redirected to the login page shortly.</p>
        <div class="countdown">Redirecting in <span id="countdown">5</span> seconds...</div>
        <div class="mt-4">
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-2"></i>Return to Login Page
            </a>
        </div>
    </div>

    <script>
        // Countdown timer for redirect
        let seconds = 5;
        const countdownElement = document.getElementById('countdown');
        
        const countdown = setInterval(function() {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdown);
                window.location.href = 'login.php';
            }
        }, 1000);
    </script>
</body>
</html>