<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
if (isset($_SESSION['diver_id'])) {
    header("Location: diver_dashboard.php");
    exit;
}
include("../includes/db.php");
include '../header.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("SELECT * FROM divers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // ✅ Check password
        if (password_verify($password, $user["password"])) {
            // ✅ Check verification status
            if ($user["verification_status"] === 'approved') {
                $_SESSION["diver_id"] = $user["id"];
                header("Location: diver_dashboard.php");
                exit;
            } elseif ($user["verification_status"] === 'pending') {
                $error = "Your account is awaiting admin approval.";
            } elseif ($user["verification_status"] === 'rejected') {
                $error = "Your registration was rejected. Please contact support.";
            } else {
                $error = "Your account status is invalid. Contact the administrator.";
            }
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "No diver found with this email.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Diver Login - DiveConnect</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-cover bg-center min-h-screen flex items-center justify-center" 
      style="background-image: url('../assets/images/dive background.jpg');">

  <div class="w-full max-w-md bg-white/95 backdrop-blur p-8 rounded-2xl shadow-2xl relative">
    <!-- Back Button -->
    <a href="../index.php" class="inline-block mb-4 text-blue-600 hover:text-blue-800 font-semibold">
      ← Back to Home
    </a>
    
    <h2 class="text-3xl font-bold text-center text-blue-700 mb-6">Dive Master Login</h2>

    <?php if (!empty($error)) : ?>
      <p class="text-red-500 text-sm mb-4"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form action="" method="POST" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Email</label>
        <input type="email" name="email" required
          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Password</label>
        <div class="relative">
          <input
            id="password"
            type="password"
            name="password"
            required
            class="w-full px-4 py-2 border rounded-lg pr-11 focus:ring-2 focus:ring-blue-500 focus:outline-none"
          >
          <!-- Eye button -->
          <button
            type="button"
            id="togglePassword"
            class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-500 hover:text-gray-700"
            aria-label="Show password"
          >
            <!-- Simple eye icon (SVG) -->
            <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              <circle cx="12" cy="12" r="3" stroke-width="1.8" stroke="currentColor" />
            </svg>
            <!-- Eye off icon -->
            <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                d="M3 3l18 18M10.477 10.49A3 3 0 0113.5 13.5m-2.39-6.115A6.993 6.993 0 0112 5c4.477 0 8.268 2.943 9.542 7a11.963 11.963 0 01-4.18 5.09M9.88 9.88A3 3 0 0012 15a3 3 0 002.12-.879M6.228 6.228A11.963 11.963 0 002.458 12c.717 2.284 2.216 4.166 4.142 5.433" />
            </svg>
          </button>
        </div>
      </div>

      <button type="submit"
        class="w-full py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
        Login
      </button>

      <!-- ✅ Forgot Password -->
      <a href="forgot_password.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
        Forgot Password?
      </a>

    </form>
  </div>

  <script>
    (function () {
      const passwordInput = document.getElementById('password');
      const toggleBtn = document.getElementById('togglePassword');
      const eyeOpen = document.getElementById('eyeOpen');
      const eyeClosed = document.getElementById('eyeClosed');

      if (passwordInput && toggleBtn) {
        toggleBtn.addEventListener('click', function () {
          const isHidden = passwordInput.type === 'password';
          passwordInput.type = isHidden ? 'text' : 'password';
          toggleBtn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');

          // Toggle icons
          if (isHidden) {
            eyeOpen.classList.add('hidden');
            eyeClosed.classList.remove('hidden');
          } else {
            eyeOpen.classList.remove('hidden');
            eyeClosed.classList.add('hidden');
          }
        });
      }
    })();
  </script>
  <script>
    history.pushState(null, "", location.href);
    window.onpopstate = function () {
        history.pushState(null, "", location.href);
    };
</script>

</body>
</html>
