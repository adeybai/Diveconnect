<?php
session_start();
include("../includes/db.php");

$message = "";
$token = $_GET["token"] ?? "";

if (empty($token)) {
  die("Invalid reset link.");
}

// ✅ Check if token is valid
$stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
  die("Invalid or expired token.");
}

$row = $result->fetch_assoc();
$email = $row["email"];
$expires = strtotime($row["expires_at"]);

if (time() > $expires) {
  die("This reset link has expired.");
}

// ✅ Handle password reset form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $new_password = trim($_POST["password"]);
  $confirm_password = trim($_POST["confirm_password"]);

  if ($new_password !== $confirm_password) {
    $message = "<p class='text-red-600'>Passwords do not match.</p>";
  } else {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);

    // Update admin password
    $update = $conn->prepare("UPDATE admins SET password = ? WHERE email = ?");
    $update->bind_param("ss", $hashed, $email);
    $update->execute();

    // Delete token after use
    $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
    $del->bind_param("s", $email);
    $del->execute();

    $message = "<p class='text-green-600 font-semibold'>Password successfully updated! You can now <a href='login_admin.php' class='text-blue-600 underline'>login</a>.</p>";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password - DiveConnect</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-cover bg-center min-h-screen flex items-center justify-center" 
      style="background-image: url('../assets/images/dive background.jpg');">

  <div class="w-full max-w-md bg-white/95 backdrop-blur p-8 rounded-2xl shadow-2xl">
    <h2 class="text-3xl font-bold text-center text-gray-800 mb-6">Reset Password</h2>

    <?= $message ?>

    <form method="POST" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">New Password</label>
        <input type="password" name="password" required
          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
        <input type="password" name="confirm_password" required
          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
      </div>

      <button type="submit"
        class="w-full py-2 px-4 bg-blue-700 hover:bg-blue-800 text-white font-semibold rounded-lg transition">
        Update Password
      </button>

      <a href="login_admin.php" class="block text-center text-sm text-blue-600 hover:underline mt-3">
        Back to Login
      </a>
    </form>
  </div>
</body>
</html>
