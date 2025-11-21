<?php
session_start();
require __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

function sendError($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function sendSuccess($message) {
    echo json_encode(['success' => true, 'message' => $message]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    sendError('Invalid request method.');
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendError('Please log in to verify your email.');
}

$user_id = $_SESSION['user_id'];
$otp = trim($_POST['otp'] ?? '');

if (empty($otp)) {
    sendError('Please enter the verification code.');
}

if (strlen($otp) !== 6 || !is_numeric($otp)) {
    sendError('Please enter a valid 6-digit code.');
}

// Verify OTP
$stmt = $conn->prepare("SELECT id, verify_token, verify_token_expires, is_email_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    sendError('User not found.');
}

if ($user['is_email_verified']) {
    sendError('Email is already verified.');
}

if (!$user['verify_token']) {
    sendError('No verification code found. Please request a new one.');
}

if ($user['verify_token'] !== $otp) {
    sendError('Invalid verification code.');
}

if (strtotime($user['verify_token_expires']) < time()) {
    sendError('Verification code has expired. Please request a new one.');
}

// Mark email as verified
$update_stmt = $conn->prepare("UPDATE users SET is_email_verified = 1, verify_token = NULL, verify_token_expires = NULL WHERE id = ?");
$update_stmt->bind_param("i", $user_id);

if ($update_stmt->execute()) {
    sendSuccess('Email verified successfully!');
} else {
    sendError('Failed to verify email. Please try again.');
}
?>