<?php
session_start();
require_once './db_config.php';

// Verify employee is logged in
if (!isset($_SESSION['employee_id'])) {
    die('Not authenticated');
}

// Retrieve messages for this employee
$employee_id = $_SESSION['employee_id'];

$query = "
    SELECT m.*, e.full_name 
    FROM chat_messages m
    JOIN employees e ON m.employee_id = e.employee_id
    WHERE m.employee_id = ?
    ORDER BY m.timestamp ASC
    LIMIT 50
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

// Mark messages as read
$update_stmt = $mysqli->prepare("
    UPDATE chat_messages 
    SET is_read = TRUE 
    WHERE employee_id = ? AND sender_type = 'bot' AND is_read = FALSE
");
$update_stmt->bind_param("i", $employee_id);
$update_stmt->execute();

if ($result->num_rows === 0) {
    echo '<div class="text-center py-3 text-muted">No messages yet. Start the conversation!</div>';
    exit;
}

while ($message = $result->fetch_assoc()) {
    $senderClass = $message['sender_type'] === 'employee' ? 'employee-message' : 'bot-message';
    $senderName = $message['sender_type'] === 'employee' ? 'You' : 'Support Bot';
    $timestamp = date('h:i A', strtotime($message['timestamp']));
    
    echo '<div class="message ' . $senderClass . '">';
    echo '<div class="message-content">' . htmlspecialchars($message['message_text']) . '</div>';
    echo '<div class="message-info">' . $senderName . ' • ' . $timestamp . '</div>';
    echo '</div>';
}

$stmt->close();
$update_stmt->close();
?>