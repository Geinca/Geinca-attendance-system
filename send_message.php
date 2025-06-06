<?php
session_start();
require_once './db_config.php';

// Verify employee is logged in
if (!isset($_SESSION['employee_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Not authenticated']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['status' => 'error', 'message' => 'Invalid request method']));
}

$message = trim($_POST['message'] ?? '');

if (empty($message)) {
    die(json_encode(['status' => 'error', 'message' => 'Empty message']));
}

// Store the user message
$employee_id = $_SESSION['employee_id'];
$sender_type = 'employee';

$stmt = $mysqli->prepare("INSERT INTO chat_messages (employee_id, sender_type, message_text) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $employee_id, $sender_type, $message);

if (!$stmt->execute()) {
    die(json_encode(['status' => 'error', 'message' => 'Failed to save user message']));
}

// Generate and store bot response
$botResponse = generateBotResponse($message);
$sender_type = 'bot';

$stmt = $mysqli->prepare("INSERT INTO chat_messages (employee_id, sender_type, message_text) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $employee_id, $sender_type, $botResponse);

if (!$stmt->execute()) {
    die(json_encode(['status' => 'error', 'message' => 'Failed to save bot response']));
}

echo json_encode(['status' => 'success']);

function generateBotResponse($userMessage) {
    $userMessage = strtolower($userMessage);
    
    $greetings = ['hi', 'hello', 'hey', 'good morning', 'good afternoon'];
    $helpRequests = ['help', 'support', 'problem', 'issue', 'question'];
    $hrKeywords = ['hr', 'human resources', 'leave', 'vacation', 'benefits'];
    $itKeywords = ['password', 'login', 'system', 'computer', 'email', 'it'];
    
    if (array_intersect(explode(' ', $userMessage), $greetings)) {
        return "Hello! I'm your Employee Support Bot. How can I assist you today?";
    } elseif (array_intersect(explode(' ', $userMessage), $helpRequests)) {
        return "I'd be happy to help. Could you please provide more details about your issue?";
    } elseif (array_intersect(explode(' ', $userMessage), $hrKeywords)) {
        return "For HR-related questions, I can provide basic information. For complex issues, I'll connect you with our HR department.";
    } elseif (array_intersect(explode(' ', $userMessage), $itKeywords)) {
        return "It sounds like you have an IT-related issue. I can submit a ticket for you or you can visit our IT support portal.";
    } elseif (strpos($userMessage, 'thank') !== false) {
        return "You're welcome! Is there anything else I can help you with?";
    } else {
        $randomResponses = [
            "I understand. Can you elaborate on that?",
            "I'll make a note of that. Is there anything specific you need help with?",
            "Thanks for sharing that information. How can I assist you further?",
            "I'm here to help with any work-related questions you might have.",
            "For security reasons, I can't access personal employee data, but I can direct you to the right resources."
        ];
        return $randomResponses[array_rand($randomResponses)];
    }
}
?>