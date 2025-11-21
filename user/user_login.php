<?php
session_start();
include '../header.php';
include("../includes/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify password first
        if (password_verify($password, $user["password"])) {
            
            // Check if email is verified
            if (!$user["is_verified"]) {
                $error = "Please verify your email address before logging in. Check your inbox for the verification link.";
            }
            // Check if admin has approved the account
            elseif (!$user["admin_approved"]) {
                $error = "Your account is pending admin approval. You will receive an email notification once your account is approved.";
            }
            // All checks passed - login successful
            else {
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["user_email"] = $user["email"];
                $_SESSION["user_name"] = $user["fullname"];
                $_SESSION["user_role"] = "user";
                
                // Redirect to dashboard
                header("Location: user_dashboard.php");
                exit;
            }
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "No user found with this email.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Login - DiveConnect</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-cover bg-center min-h-screen flex items-center justify-center" 
      style="background-image: url('../assets/images/dive background.jpg');">

  <div class="w-full max-w-md bg-white/95 backdrop-blur p-8 rounded-2xl shadow-2xl relative">
    <!-- Back Button -->
    <a href="../index.php" class="inline-block mb-4 text-green-700 hover:text-green-900 font-semibold">
      ← Back to Home
    </a>
    <h2 class="text-3xl font-bold text-center text-green-700 mb-6">Diver Login</h2>

    <?php if (!empty($error)) : ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?= htmlspecialchars($error) ?>
        
        <?php if (strpos($error, 'verify your email') !== false): ?>
          <div class="mt-2">
            <a href="resend_verification.php" class="text-blue-600 hover:text-blue-800 text-sm underline">
              Resend verification email
            </a>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php 
        echo $_SESSION['success_message']; 
        unset($_SESSION['success_message']);
        ?>
      </div>
    <?php endif; ?>

    <form action="" method="POST" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Email</label>
        <input type="email" name="email" required
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:outline-none"
          placeholder="your@email.com"
          value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
      </div>
      
      <div>
        <label class="block text-sm font-medium text-gray-700">Password</label>
        <input type="password" name="password" required
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:outline-none"
          placeholder="Enter your password">
      </div>

      <button type="submit"
        class="w-full py-3 px-4 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition duration-200">
        Login
      </button>

      <div class="text-center">
        <a href="forgot_password_user.php" class="text-sm text-green-600 hover:underline mt-2">
          Forgot Password?
        </a>
      </div>
    </form>

    <p class="text-center text-sm text-gray-600 mt-6">
      Don't have an account? 
      <a href="register_user.php" class="text-green-600 hover:underline font-medium">Register here</a>
    </p>
  </div>

  <script>
    // Show SweetAlert for specific error messages
    <?php if (!empty($error)): ?>
      <?php if (strpos($error, 'verify your email') !== false): ?>
        Swal.fire({
          icon: 'warning',
          title: 'Email Not Verified',
          text: '<?= addslashes($error) ?>',
          confirmButtonText: 'OK',
          confirmButtonColor: '#d33'
        });
      <?php elseif (strpos($error, 'pending admin approval') !== false): ?>
        Swal.fire({
          icon: 'info',
          title: 'Pending Approval',
          text: '<?= addslashes($error) ?>',
          confirmButtonText: 'OK',
          confirmButtonColor: '#3085d6'
        });
      <?php endif; ?>
    <?php endif; ?>
  </script>
</body>
</html>