<?php
session_start();
include("../includes/db.php");
require '../library/mailer.php';
use DiveConnect\Mailer;


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user["password"])) {
            $_SESSION["admin_id"] = $user["admin_id"]; 
            $_SESSION["admin_email"] = $user["email"];
            $_SESSION['role'] = $user['role'];

            var_dump($_SESSION);
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "No admin found with this email.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-cover bg-center min-h-screen flex items-center justify-center" 
      style="background-image: url('../assets/images/dive background.jpg');">

  <div class="w-full max-w-md bg-white p-8 rounded-2xl shadow-2xl relative">
    <!-- Back Button -->
    <a href="../index.php" class="inline-block mb-4 text-blue-600 hover:text-blue-800 font-semibold">
      ← Back to Home
    </a>
    <h2 class="text-3xl font-bold text-center text-gray-800 mb-6">Admin Login</h2>

    <?php if (!empty($error)) : ?>
      <p class="text-red-500 text-sm mb-4"><?= $error ?></p>
    <?php endif; ?>

    <form action="" method="POST" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Email</label>
        <input type="email" name="email" required
          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
      </div>

      <!-- PASSWORD WITH EYE TOGGLE -->
      <div>
        <label class="block text-sm font-medium text-gray-700">Password</label>
        <div class="mt-1 relative">
          <input
            id="password"
            type="password"
            name="password"
            required
            class="w-full px-4 py-2 pr-10 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
          >
          <button
            type="button"
            onclick="togglePasswordVisibility()"
            class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-500 hover:text-gray-700"
            aria-label="Toggle password visibility"
          >
            <!-- eye open -->
            <svg id="eye-open" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              <circle cx="12" cy="12" r="3" stroke-width="2" stroke="currentColor" fill="none" />
            </svg>
            <!-- eye closed -->
            <svg id="eye-closed" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 3l18 18M10.477 10.49A3 3 0 0113.5 13.5M9.88 9.88A3 3 0 0012 15c4.477 0 8.268-2.943 9.542-7a11.973 11.973 0 00-4.304-5.045M6.228 6.228A11.955 11.955 0 002.458 12c1.274 4.057 5.065 7 9.542 7 1.338 0 2.62-.236 3.804-.669" />
            </svg>
          </button>
        </div>
      </div>

      <button type="submit"
        class="w-full py-2 px-4 bg-gray-800 hover:bg-gray-900 text-white font-semibold rounded-lg transition">
        Login
      </button>
      <a href="forgot_password.php" class="block text-center text-sm text-blue-600 hover:underline">
        Forgot Password?
      </a>

    </form>
  </div>

  <script>
    function togglePasswordVisibility() {
      const input = document.getElementById('password');
      const eyeOpen = document.getElementById('eye-open');
      const eyeClosed = document.getElementById('eye-closed');

      if (!input) return;

      if (input.type === 'password') {
        input.type = 'text';
        eyeOpen.classList.add('hidden');
        eyeClosed.classList.remove('hidden');
      } else {
        input.type = 'password';
        eyeClosed.classList.add('hidden');
        eyeOpen.classList.remove('hidden');
      }
    }
  </script>
</body>
</html>
