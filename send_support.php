<?php
session_start();
require 'includes/db.php';
require 'library/mailer.php';
use DiveConnect\Mailer;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        echo "<script>
            alert('⚠️ Please fill out all fields.');
            window.history.back();
        </script>";
        exit;
    }

    try {
        $mailer = new Mailer();
        $sent = $mailer->sendSupportMessage($name, $email, $message);

        if ($sent) {
            echo "<script>
                alert('✅ Your message has been sent successfully! Our support team will contact you soon.');
                window.location.href='index.php';
            </script>";
        } else {
            echo "<script>
                alert('❌ Failed to send message. Please try again later.');
                window.history.back();
            </script>";
        }
    } catch (Exception $e) {
        echo "<script>
            alert('❌ An unexpected error occurred.');
            window.history.back();
        </script>";
    }
} else {
    header('Location: index.php');
    exit;
}
?>
