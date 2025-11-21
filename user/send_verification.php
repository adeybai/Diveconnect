<?php
session_start();
require __DIR__ . '/../includes/db.php';

if (!file_exists('../library/mailer.php')) {
    die(json_encode(['success' => false, 'message' => 'Mailer configuration missing']));
}
require_once '../library/mailer.php';

header('Content-Type: application/json');

function sendError($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function sendSuccess($message) {
    echo json_encode(['success' => true, 'message' => $message]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendError('Please log in to verify your email.');
}

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $conn->prepare("SELECT email, fullname, is_email_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    sendError('User not found.');
}

// Check if already verified
if ($user['is_email_verified']) {
    sendError('Email is already verified.');
}

// Generate OTP
$otp = sprintf("%06d", mt_rand(1, 999999));
$otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Store OTP in database
$update_stmt = $conn->prepare("UPDATE users SET verify_token = ?, verify_token_expires = ? WHERE id = ?");
$update_stmt->bind_param("ssi", $otp, $otp_expires, $user_id);

if ($update_stmt->execute()) {
    // Send OTP via email
    try {
        $mailer = new DiveConnect\Mailer();
        $emailSent = $mailer->sendVerificationOTP($user['email'], $user['fullname'], $otp);
        
        if ($emailSent) {
            sendSuccess('Verification OTP sent to your email! Check your inbox.');
        } else {
            sendError('Failed to send verification email. Please try again.');
        }
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        sendError('Email service temporarily unavailable. Please try again later.');
    }
} else {
    sendError('Failed to generate verification code. Please try again.');
}
?>