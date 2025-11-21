<?php
session_start();
include("../includes/db.php");
require '../library/mailer.php';
use DiveConnect\Mailer;

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);

    $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $conn->query("DELETE FROM password_resets WHERE email = '$email'");
        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $token, $expires);
        $stmt->execute();

        $resetLink = "https://diveconnect.site/admin/reset_password.php?token=$token";
        $mailer = new Mailer();
        if ($mailer->sendPasswordReset($email, $resetLink)) {
            $message = "<p class='text-green-600'>Password reset link has been sent to your email.</p>";
        } else {
            $message = "<p class='text-red-600'>Failed to send reset email. Please try again.</p>";
        }
    } else {
        $message = "<p class='text-red-600'>No admin found with that email.</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
  <div class="w-full max-w-md bg-white p-8 rounded-2xl shadow-lg">
    <h2 class="text-2xl font-bold text-center mb-6 text-gray-800">Forgot Password</h2>
    <?= $message ?>
    <form method="POST" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Email</label>
        <input type="email" name="email" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
      </div>
      <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg">Send Reset Link</button>
      <a href="login.php" class="block text-center text-sm text-blue-600 mt-2 hover:underline">Back to Login</a>
    </form>
  </div>
</body>
</html>
