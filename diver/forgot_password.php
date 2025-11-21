<?php
session_start();
include("../includes/db.php");
require '../library/mailer.php';
use DiveConnect\Mailer;

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);

    // ✅ Check if diver exists
    $stmt = $conn->prepare("SELECT * FROM divers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // ✅ Generate reset token and expiry
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // ✅ Remove old token (optional)
        $conn->query("UPDATE divers SET reset_token = NULL, token_expiry = NULL WHERE email = '$email'");

        // ✅ Save new token
        $stmt = $conn->prepare("UPDATE divers SET reset_token = ?, token_expiry = ? WHERE email = ?");
        $stmt->bind_param("sss", $token, $expires, $email);
        $stmt->execute();

        // ✅ Reset link
       
        $resetLink = "https://diveconnect.site/diver/reset_password.php?token=$token";


        // ✅ Use your Mailer class
        $mailer = new Mailer();
        if ($mailer->sendPasswordReset($email, $resetLink)) {
            $message = "<p class='text-green-600 text-center'>Password reset link sent to your email.</p>";
        } else {
            $message = "<p class='text-red-600 text-center'>Failed to send email. Please try again.</p>";
        }
    } else {
        $message = "<p class='text-red-600 text-center'>No account found with that email.</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Diver Forgot Password - DiveConnect</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-cover bg-center min-h-screen flex items-center justify-center" 
      style="background-image: url('../assets/images/dive background.jpg');">

  <div class="w-full max-w-md bg-white/95 backdrop-blur p-8 rounded-2xl shadow-2xl">
    <a href="login_diver.php" class="inline-block mb-4 text-blue-600 hover:text-blue-800 font-semibold">
      ← Back to Login
    </a>

    <h2 class="text-2xl font-bold text-center text-blue-700 mb-4">Forgot Password</h2>
    <?= $message ?>

    <form method="POST" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Email</label>
        <input type="email" name="email" required
          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
      </div>

      <button type="submit"
        class="w-full py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
        Send Reset Link
      </button>
    </form>
  </div>
</body>
</html>
