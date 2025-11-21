<?php
session_start();
include("../includes/db.php");

$message = "";
$success = false;
$token = $_GET["token"] ?? "";

if (empty($token)) die("Invalid reset link.");

// ✅ Verify token
$stmt = $conn->prepare("SELECT email, token_expiry FROM divers WHERE reset_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) die("Invalid or expired token.");

$row = $result->fetch_assoc();
$email = $row["email"];
$expiry = strtotime($row["token_expiry"]);

if (time() > $expiry) die("This reset link has expired.");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $new_password = trim($_POST["password"]);
  $confirm_password = trim($_POST["confirm_password"]);

  if ($new_password !== $confirm_password) {
    $message = "<p class='text-red-600 text-center'>Passwords do not match.</p>";
  } else {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE divers SET password = ?, reset_token = NULL, token_expiry = NULL WHERE email = ?");
    $stmt->bind_param("ss", $hashed, $email);
    $stmt->execute();

    $success = true;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Diver Reset Password - DiveConnect</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-cover bg-center min-h-screen flex items-center justify-center" 
      style="background-image: url('../assets/images/dive background.jpg');">

  <div class="w-full max-w-md bg-white/95 backdrop-blur p-8 rounded-2xl shadow-2xl">
    <h2 class="text-3xl font-bold text-center text-blue-700 mb-6">Reset Password</h2>

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
        class="w-full py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
        Update Password
      </button>
    </form>
  </div>

  <?php if ($success): ?>
  <script>
    Swal.fire({
      title: 'Password Updated!',
      text: 'You can now log in with your new password.',
      icon: 'success',
      confirmButtonColor: '#2563eb'
    }).then(() => {
      window.location.href = 'login_diver.php';
    });
  </script>
  <?php endif; ?>
</body>
</html>
